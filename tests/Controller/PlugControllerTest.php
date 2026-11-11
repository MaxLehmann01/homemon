<?php

namespace App\Tests\Controller;

use App\Entity\ShellyPlug;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlugControllerTest extends WebTestCase
{
    private function resetSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testPlugCanBeCreatedEditedAndDeletedThroughThePages(): void
    {
        $client = static::createClient();
        $this->resetSchema();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Meine Plugs');
        self::assertSelectorTextContains('main', 'Noch keine Plugs angelegt');

        $crawler = $client->clickLink('Plug hinzufügen');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Plug erstellen')->form([
            'shelly_plug[name]' => '',
            'shelly_plug[hostname]' => '',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.plug-form', 'Bitte gib einen Namen ein.');

        $form = $crawler->selectButton('Plug erstellen')->form([
            'shelly_plug[name]' => 'Wohnzimmerlampe',
            'shelly_plug[hostname]' => 'shelly-wohnzimmer.local',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Wohnzimmerlampe');
        self::assertSelectorTextContains('main', 'shelly-wohnzimmer.local');

        $crawler = $client->clickLink('Bearbeiten');
        $form = $crawler->selectButton('Änderungen speichern')->form([
            'shelly_plug[name]' => 'Lampe im Wohnzimmer',
            'shelly_plug[hostname]' => '192.168.1.42',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Lampe im Wohnzimmer');
        self::assertSelectorTextContains('main', '192.168.1.42');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $plug = $entityManager->getRepository(ShellyPlug::class)->findOneBy(['name' => 'Lampe im Wohnzimmer']);
        self::assertInstanceOf(ShellyPlug::class, $plug);

        $crawler = $client->request('GET', '/');
        $deleteForm = $crawler->filter(sprintf('form[action="/plugs/%s/loeschen"]', $plug->getId()))->form();
        $client->submit($deleteForm);
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Noch keine Plugs angelegt');
    }
}
