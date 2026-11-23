<?php

namespace App\Entity;

use App\Repository\ShellyPlugRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShellyPlugRepository::class)]
class ShellyPlug
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter a name.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter a URL.')]
    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false, message: 'Please enter a valid HTTP or HTTPS URL.')]
    #[Assert\Regex(pattern: '~^https?://[^?#@]+$~i', message: 'Use a base URL without credentials, a query string or a fragment.')]
    private ?string $hostname = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $status = null;

    #[ORM\Column(type: 'json')]
    private array $powerHistory = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $collectionError = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(string $hostname): static
    {
        $hostname = rtrim(trim($hostname), '/');
        if ($this->hostname !== $hostname) {
            $this->status = null;
            $this->powerHistory = [];
            $this->checkedAt = null;
            $this->collectionError = null;
        }
        $this->hostname = $hostname;

        return $this;
    }

    public function getStatus(): ?array
    {
        return $this->status;
    }

    public function getPowerHistory(): array
    {
        return $this->powerHistory;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function getCollectionError(): ?string
    {
        return $this->collectionError;
    }

    public function recordReading(?array $status, \DateTimeImmutable $at, ?string $error = null): void
    {
        $this->status = $status;
        $this->checkedAt = $at;
        $this->collectionError = $error;
        $this->powerHistory = array_values(array_filter($this->powerHistory, static fn (array $point): bool => $point['time'] > $at->getTimestamp() - 300));
        $this->powerHistory[] = ['time' => $at->getTimestamp(), 'watts' => $status['apower'] ?? null];
        $this->powerHistory = array_slice($this->powerHistory, -300);
    }
}
