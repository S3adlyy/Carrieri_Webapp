<?php

namespace App\Controller\DashboardController;

use App\Entity\Reclamation;
use App\Entity\TraitementReclamation;
use App\Form\TraitementReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\TraitementReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/traitement')]
class TraitementReclamationController extends AbstractController  // ← Changé ici
{
    private function checkRecruiter(): void
    {
        $user = $this->getUser();
        if (!$user || $user->getType() !== 'RECRUITER') {
            throw $this->createAccessDeniedException('Accès réservé aux recruteurs');
        }
    }

    #[Route('/reclamations', name: 'app_dashboard_traitement_reclamations', methods: ['GET'])]
    public function reclamations(ReclamationRepository $reclamationRepository): Response
    {
        $this->checkRecruiter();
        
        $reclamations = $reclamationRepository->createQueryBuilder('r')
            ->where('r.statut != :statut OR r.statut IS NULL')
            ->setParameter('statut', 'Traité')
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('BackOffice/dashboard/traitement/reclamations.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/traiter/{id}', name: 'app_dashboard_traitement_traiter', methods: ['GET', 'POST'])]
    public function traiter(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        
        $existingTraitement = $em->getRepository(TraitementReclamation::class)->findOneBy(['reclamation' => $reclamation]);
        
        if ($existingTraitement) {
            $this->addFlash('warning', 'Cette réclamation a déjà été traitée');
            return $this->redirectToRoute('app_dashboard_traitement_reclamations');
        }
        
        $traitement = new TraitementReclamation();
        $form = $this->createForm(TraitementReclamationType::class, $traitement);
        
        $traitement->setDateTraitement(new \DateTimeImmutable());
        $traitement->setReclamation($reclamation);
        $traitement->setReclamationId($reclamation->getId());
        $traitement->setAdminId($user->getId());
        $traitement->setUser($user);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $reclamation->setStatut('Traité');
            
            $em->persist($traitement);
            $em->flush();
            
            $this->addFlash('success', 'La réclamation a été traitée avec succès');
            return $this->redirectToRoute('app_dashboard_traitement_reclamations');
        }
        
        return $this->render('BackOffice/dashboard/traitement/traiter.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/', name: 'app_dashboard_traitement_index', methods: ['GET'])]
    public function index(TraitementReclamationRepository $repository): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        $traitements = $repository->findBy(['user' => $user], ['dateTraitement' => 'DESC']);
        
        return $this->render('BackOffice/dashboard/traitement/index.html.twig', [
            'traitements' => $traitements,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_traitement_show', methods: ['GET'])]
    public function show(TraitementReclamation $traitement): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        return $this->render('BackOffice/dashboard/traitement/show.html.twig', [
            'traitement' => $traitement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TraitementReclamation $traitement, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce traitement');
        }
        
        $form = $this->createForm(TraitementReclamationType::class, $traitement);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le traitement a été modifié avec succès');
            return $this->redirectToRoute('app_dashboard_traitement_index');
        }
        
        return $this->render('BackOffice/dashboard/traitement/edit.html.twig', [
            'form' => $form->createView(),
            'traitement' => $traitement,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_traitement_delete', methods: ['POST'])]
    public function delete(Request $request, TraitementReclamation $traitement, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce traitement');
        }
        
        if ($this->isCsrfTokenValid('delete' . $traitement->getId(), $request->request->get('_token'))) {
            $reclamation = $traitement->getReclamation();
            if ($reclamation) {
                $reclamation->setStatut('En attente');
            }
            
            $em->remove($traitement);
            $em->flush();
            $this->addFlash('success', 'Le traitement a été supprimé avec succès');
        }
        
        return $this->redirectToRoute('app_dashboard_traitement_index');
    }
}