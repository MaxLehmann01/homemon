<?php

namespace App\Tests\Command;

use App\Entity\ShellyPlug;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CollectPlugsCommandTest extends KernelTestCase
{
    public function testOnceCollectsReadingsAndRejectsADuplicateCollector(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $em->persist((new ShellyPlug())->setName('Desk')->setHostname('http://desk.local'));
        $em->flush();
        $container->set(HttpClientInterface::class, new MockHttpClient(static fn () => new MockResponse('{"id":0,"output":false,"apower":0,"voltage":230,"current":0,"aenergy":{"total":12},"temperature":{"tC":30}}')));

        $tester = new CommandTester((new Application(self::$kernel))->find('app:plugs:collect'));
        $tester->execute(['--once' => true]);
        $tester->assertCommandIsSuccessful();
        $plug = $em->getRepository(ShellyPlug::class)->findOneBy(['name' => 'Desk']);
        self::assertSame(0, $plug->getStatus()['apower']);

        $lock = $container->get(LockFactory::class)->createLock('homemon.plug-collector', 30);
        self::assertTrue($lock->acquire());
        try {
            self::assertSame(1, $tester->execute(['--once' => true]));
            self::assertStringContainsString('already running', $tester->getDisplay());
        } finally {
            $lock->release();
        }
    }
}
