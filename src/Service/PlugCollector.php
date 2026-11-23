<?php

namespace App\Service;

use App\Repository\ShellyPlugRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PlugCollector
{
    public function __construct(
        private ShellyPlugRepository $plugs,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private ClockInterface $clock,
    ) {
    }

    public function collect(): int
    {
        // Reload configuration on every tick, including plugs added while running.
        $this->entityManager->clear();
        $pending = [];
        $plugs = $this->plugs->findAll();
        foreach ($plugs as $plug) {
            try {
                $pending[] = [$plug, $this->httpClient->request('GET', $plug->getHostname().'/rpc/Switch.GetStatus?id=0', [
                    'timeout' => 0.8,
                    'max_duration' => 0.8,
                    'max_redirects' => 0,
                    'headers' => ['Accept' => 'application/json'],
                ])];
            } catch (ExceptionInterface|\InvalidArgumentException $error) {
                $plug->recordReading(null, $this->clock->now(), 'Unable to request plug status. Check the configured URL.');
            }
        }

        // Requests are dispatched together; an offline plug cannot delay each other request.
        foreach ($pending as [$plug, $response]) {
            try {
                $status = $response->toArray();
                $this->validateStatus($status);
                $plug->recordReading($status, $this->clock->now());
            } catch (ExceptionInterface|\UnexpectedValueException $error) {
                $plug->recordReading(null, $this->clock->now(), 'Unable to read plug status. Check the URL and device connection.');
            }
        }

        $this->entityManager->flush();

        return count($plugs);
    }

    private function validateStatus(array $status): void
    {
        if (0 !== ($status['id'] ?? null) || !is_bool($status['output'] ?? null)) {
            throw new \UnexpectedValueException('Invalid switch status.');
        }
        foreach ([$status['apower'] ?? null, $status['voltage'] ?? null, $status['current'] ?? null, $status['aenergy']['total'] ?? null, $status['temperature']['tC'] ?? null] as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new \UnexpectedValueException('Invalid measurement.');
            }
        }
    }
}
