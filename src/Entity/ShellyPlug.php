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
    #[Assert\NotBlank(message: 'Bitte gib einen Namen ein.')]
    #[Assert\Length(max: 255, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen lang sein.')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Bitte gib einen Hostnamen oder eine IP-Adresse ein.')]
    #[Assert\Length(max: 255, maxMessage: 'Der Hostname darf höchstens {{ limit }} Zeichen lang sein.')]
    private ?string $hostname = null;

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
        $this->hostname = $hostname;

        return $this;
    }
}
