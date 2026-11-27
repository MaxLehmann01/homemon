<?php

namespace App\Twig\Components;

use App\Entity\ShellyPlug;
use App\Form\ShellyPlugType;
use App\Repository\ShellyPlugRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class PlugDashboard extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public bool $showForm = false;

    #[LiveProp]
    public ?string $editingId = null;

    #[LiveProp]
    public string $notice = '';

    public function __construct(private readonly ShellyPlugRepository $plugs, private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getPlugs(): array
    {
        return $this->plugs->findBy([], ['name' => 'ASC']);
    }

    #[LiveAction]
    public function add(): void
    {
        $this->editingId = null;
        $this->showForm = true;
        $this->notice = '';
        $this->resetForm();
    }

    #[LiveAction]
    public function edit(#[LiveArg] string $id): void
    {
        $this->findPlug($id);
        $this->editingId = $id;
        $this->showForm = true;
        $this->notice = '';
        $this->resetForm();
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();
        $plug = $this->getForm()->getData();
        $this->entityManager->persist($plug);
        $this->entityManager->flush();
        $this->notice = sprintf('“%s” saved.', $plug->getName());
        $this->cancel();
    }

    #[LiveAction]
    public function delete(#[LiveArg] string $id): void
    {
        $plug = $this->findPlug($id);
        $this->notice = sprintf('“%s” deleted.', $plug->getName());
        $this->entityManager->remove($plug);
        $this->entityManager->flush();
        $this->cancel();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ShellyPlugType::class, $this->editingId ? $this->findPlug($this->editingId) : new ShellyPlug(), ['is_edit' => null !== $this->editingId]);
    }

    private function findPlug(string $id): ShellyPlug
    {
        return $this->plugs->find($id) ?? throw $this->createNotFoundException('Plug not found.');
    }
}
