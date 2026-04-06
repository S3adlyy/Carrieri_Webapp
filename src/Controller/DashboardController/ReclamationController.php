<?php

namespace App\Controller\DashboardController;

use App\Entity\Reclamation;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $repository): Response
    {
        return $this->render('BackOffice/dashboard/reclamations/index.html.twig', [
            'reclamations' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_dashboard_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reclamation->setDateCreation(new \DateTimeImmutable());
            $em->persist($reclamation);
            $em->flush();
            $this->addFlash('success', 'Réclamation créée avec succès');
            return $this->redirectToRoute('app_dashboard_reclamation_index');
        }

        return $this->render('BackOffice/dashboard/reclamations/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('BackOffice/dashboard/reclamations/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Réclamation modifiée avec succès');
            return $this->redirectToRoute('app_dashboard_reclamation_index');
        }

        return $this->render('BackOffice/dashboard/reclamations/edit.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $em->remove($reclamation);
            $em->flush();
            $this->addFlash('success', 'Réclamation supprimée avec succès');
        }
        return $this->redirectToRoute('app_dashboard_reclamation_index');
    }
}