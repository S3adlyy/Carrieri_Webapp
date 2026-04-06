<?php

namespace App\Controller\FrontOffice;

use App\Entity\Reclamation;
use App\Form\FrontOffice\ReclamationType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat/mes-reclamations')]  // ← NOUVELLE ROUTE
#[IsGranted('ROLE_CANDIDAT')]
class ReclamationController extends AbstractController
{
    #[Route('/', name: 'app_candidat_reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $repository): Response
    {
        $user = $this->getUser();
        
        $reclamations = $repository->findBy(['user' => $user], ['dateCreation' => 'DESC']);
        
        return $this->render('FrontOffice/main/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }
    
    #[Route('/new', name: 'app_candidat_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        
       $reclamation->setDateCreation(new \DateTimeImmutable());
$reclamation->setStatut('En attente');
$reclamation->setPriorite('Moyenne');
$reclamation->setCategorie('Autre');  // ← AJOUTE CETTE LIGNE
$reclamation->setUser($user);
$reclamation->setUtilisateurId($user->getId());
$reclamation->setEmail($user->getEmail());
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reclamation);
            $em->flush();
            
            $this->addFlash('success', 'Votre réclamation a été envoyée');
            return $this->redirectToRoute('app_candidat_reclamation_index');
        }
        
        return $this->render('FrontOffice/main/reclamation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/{id}', name: 'app_candidat_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        $user = $this->getUser();
        
        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        return $this->render('FrontOffice/main/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }
    
    #[Route('/{id}/edit', name: 'app_candidat_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette réclamation');
        }
        
        if ($reclamation->getStatut() === 'Traité') {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier une réclamation déjà traitée');
            return $this->redirectToRoute('app_candidat_reclamation_index');
        }
        
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre réclamation a été modifiée');
            return $this->redirectToRoute('app_candidat_reclamation_index');
        }
        
        return $this->render('FrontOffice/main/reclamation/edit.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
        ]);
    }
    
    #[Route('/{id}', name: 'app_candidat_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette réclamation');
        }
        
        if ($reclamation->getStatut() === 'Traité') {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer une réclamation déjà traitée');
            return $this->redirectToRoute('app_candidat_reclamation_index');
        }
        
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $em->remove($reclamation);
            $em->flush();
            $this->addFlash('success', 'Votre réclamation a été supprimée');
        }
        
        return $this->redirectToRoute('app_candidat_reclamation_index');
    }
}