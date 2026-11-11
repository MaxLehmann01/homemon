<?php

namespace App\Controller;

use App\Entity\ShellyPlug;
use App\Form\ShellyPlugType;
use App\Repository\ShellyPlugRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/', name: 'plug_index', methods: ['GET'])]
    public function index(ShellyPlugRepository $plugs): Response
    {
        return $this->render('plug/index.html.twig', [
            'plugs' => $plugs->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/plugs/neu', name: 'plug_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $plug = new ShellyPlug();
        $form = $this->createForm(ShellyPlugType::class, $plug);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($plug);
            $entityManager->flush();

            $this->addFlash('success', sprintf('„%s“ wurde erstellt.', $plug->getName()));

            return $this->redirectToRoute('plug_index');
        }

        return $this->render('plug/form.html.twig', [
            'form' => $form,
            'plug' => $plug,
            'is_edit' => false,
        ]);
    }

    #[Route('/plugs/{id}/bearbeiten', name: 'plug_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, ShellyPlugRepository $plugs, EntityManagerInterface $entityManager): Response
    {
        $plug = $plugs->find($id);
        if (!$plug instanceof ShellyPlug) {
            throw $this->createNotFoundException('Der Plug wurde nicht gefunden.');
        }

        $form = $this->createForm(ShellyPlugType::class, $plug, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('„%s“ wurde aktualisiert.', $plug->getName()));

            return $this->redirectToRoute('plug_index');
        }

        return $this->render('plug/form.html.twig', [
            'form' => $form,
            'plug' => $plug,
            'is_edit' => true,
        ]);
    }

    #[Route('/plugs/{id}/loeschen', name: 'plug_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, ShellyPlugRepository $plugs, EntityManagerInterface $entityManager): Response
    {
        $plug = $plugs->find($id);
        if (!$plug instanceof ShellyPlug) {
            throw $this->createNotFoundException('Der Plug wurde nicht gefunden.');
        }

        if ($this->isCsrfTokenValid('delete'.$plug->getId(), $request->request->getString('_token'))) {
            $name = $plug->getName();
            $entityManager->remove($plug);
            $entityManager->flush();
            $this->addFlash('success', sprintf('„%s“ wurde gelöscht.', $name));
        } else {
            $this->addFlash('error', 'Der Plug konnte nicht gelöscht werden. Bitte versuche es erneut.');
        }

        return $this->redirectToRoute('plug_index');
    }
}
