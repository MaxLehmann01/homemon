<?php

namespace App\Tests\Controller;

use App\Entity\ShellyPlug;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PlugControllerTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private function resetSchema(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testPlugCanBeCreatedEditedAndDeletedOnTheDashboard(): void
    {
        $client = static::createClient();
        $this->resetSchema();
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['password' => 'test-password']);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Energy dashboard');
        self::assertSelectorTextContains('main', 'No plugs yet');

        $dashboard = $this->createLiveComponent('PlugDashboard', client: $client);
        // The test factory mounts forms outside an HTTP request.
        $request = Request::create('/');
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        $stack = static::getContainer()->get(RequestStack::class);
        $stack->push($request);
        $dashboard->call('add');
        $stack->pop();
        $client->catchExceptions(true);
        self::assertResponseIsSuccessful();
        $dashboard->submitForm(['shelly_plug' => ['name' => '', 'hostname' => '']], 'save');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.plug-form', 'Please enter a name.');

        $dashboard->submitForm(['shelly_plug' => ['name' => 'Living room', 'hostname' => 'ftp://plug.local']], 'save');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.plug-form', 'Please enter a valid HTTP or HTTPS URL.');

        $dashboard->submitForm(['shelly_plug' => ['name' => 'Living room', 'hostname' => 'http://plug.local/']], 'save');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Living room');
        self::assertSelectorExists('[data-poll="delay(1000)|$render"]');
        self::assertSelectorExists('canvas');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $plug = $em->getRepository(ShellyPlug::class)->findOneBy(['name' => 'Living room']);
        self::assertSame('http://plug.local', $plug->getHostname());
        $id = (string) $plug->getId();

        $dashboard->call('edit', ['id' => $id]);
        $dashboard->submitForm(['shelly_plug' => ['name' => 'Desk', 'hostname' => 'https://192.168.1.42:8443']], 'save');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Desk');
        self::assertSelectorTextContains('.plug-hostname', 'https://192.168.1.42:8443');
        $dashboard->call('delete', ['id' => $id]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section', 'No plugs yet');
    }

    public function testLiveCardRefreshesWattOnlyGraphAndShowsStaleState(): void
    {
        $client = static::createClient();
        $this->resetSchema();
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['password' => 'test-password']);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $plug = (new ShellyPlug())->setName('Coffee machine')->setHostname('http://coffee.local');
        $status = ['id' => 0, 'apower' => 125.5, 'output' => true, 'voltage' => 230, 'current' => 0.54, 'aenergy' => ['total' => 12000], 'temperature' => ['tC' => 30.4]];
        $plug->recordReading($status, new \DateTimeImmutable());
        $em->persist($plug);
        $em->flush();
        $id = $plug->getId();

        $card = $this->createLiveComponent('PlugCard', ['plug' => $plug], $client);
        $card->render();
        self::assertSelectorTextContains('.power-value', '125.5');
        self::assertSelectorTextContains('.badge', 'Live');
        $view = json_decode($client->getCrawler()->filter('canvas')->attr('data-symfony--ux-chartjs--chart-view-value'), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $view['data']['datasets']);
        self::assertSame('Power (W)', $view['data']['datasets'][0]['label']);
        self::assertContains(125.5, $view['data']['datasets'][0]['data']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $plug = $em->find(ShellyPlug::class, $id);
        $plug->recordReading(array_replace($status, ['apower' => 0]), new \DateTimeImmutable());
        $em->flush();
        $card->refresh();
        self::assertSelectorTextContains('.power-value', '0.0');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $plug = $em->find(ShellyPlug::class, $id);
        $plug->recordReading($status, new \DateTimeImmutable('-10 seconds'));
        $em->flush();
        $card->refresh();
        self::assertSelectorTextContains('.badge', 'Stale');
        self::assertSelectorTextContains('.power-value', '—');
    }

    public function testSignOutRevokesDashboardAccess(): void
    {
        $client = static::createClient();
        $this->resetSchema();
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['password' => 'test-password']);
        $client->request('GET', '/');
        $client->submitForm('Sign out');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }
}
