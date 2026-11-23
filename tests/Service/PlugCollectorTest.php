<?php

namespace App\Tests\Service;

use App\Entity\ShellyPlug;
use App\Service\PlugCollector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PlugCollectorTest extends KernelTestCase
{
    public function testCollectionPersistsMeasurementsAndIsolatesOfflineAndInvalidPlugs(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        foreach (['online', 'offline', 'invalid'] as $name) {
            $em->persist((new ShellyPlug())->setName($name)->setHostname('http://'.$name.'.local'));
        }
        $em->flush();

        $reading = ['id' => 0, 'output' => false, 'apower' => 42.5, 'voltage' => 230, 'current' => 0.18, 'aenergy' => ['total' => 12756.628], 'temperature' => ['tC' => 30.4]];
        $http = new MockHttpClient(static function (string $method, string $url) use ($reading): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringEndsWith('/rpc/Switch.GetStatus?id=0', $url);

            return match (true) {
                str_contains($url, 'offline') => new MockResponse('', ['error' => 'Connection refused']),
                str_contains($url, 'invalid') => new MockResponse('{"apower":"bad"}'),
                default => new MockResponse(json_encode($reading, JSON_THROW_ON_ERROR)),
            };
        });
        $container->set(HttpClientInterface::class, $http);
        $collector = $container->get(PlugCollector::class);
        self::assertSame(3, $collector->collect());

        $online = $em->getRepository(ShellyPlug::class)->findOneBy(['name' => 'online']);
        self::assertSame(42.5, $online->getStatus()['apower']);
        self::assertFalse($online->getStatus()['output']);
        self::assertSame(42.5, $online->getPowerHistory()[0]['watts']);
        self::assertNull($online->getCollectionError());
        foreach (['offline', 'invalid'] as $name) {
            $failed = $em->getRepository(ShellyPlug::class)->findOneBy(['name' => $name]);
            self::assertNotNull($failed->getCollectionError());
            self::assertNull($failed->getPowerHistory()[0]['watts']);
        }

        // Discover a plug added after startup and retain actual zero-watt readings.
        $em->persist((new ShellyPlug())->setName('new')->setHostname('https://new.local'));
        $em->flush();
        $http->setResponseFactory(static fn () => new MockResponse(json_encode(array_replace($reading, ['apower' => 0]), JSON_THROW_ON_ERROR)));
        self::assertSame(4, $collector->collect());
        $online = $em->getRepository(ShellyPlug::class)->findOneBy(['name' => 'online']);
        self::assertSame(0, $online->getStatus()['apower']);
        self::assertCount(2, $online->getPowerHistory());
    }

    public function testHistoryIsBoundedAndChangingUrlResetsReadings(): void
    {
        self::bootKernel();
        $clock = new MockClock('2026-11-23 12:00:00 UTC');
        $plug = (new ShellyPlug())->setHostname('http://plug.local/');
        for ($i = 0; $i < 400; ++$i) {
            $plug->recordReading(['apower' => $i], $clock->now());
            $clock->sleep(1);
        }
        self::assertCount(300, $plug->getPowerHistory());
        self::assertSame(100, $plug->getPowerHistory()[0]['watts']);
        self::assertSame('http://plug.local', $plug->getHostname());
        $plug->setHostname('https://different.local');
        self::assertNull($plug->getStatus());
        self::assertSame([], $plug->getPowerHistory());
    }
}
