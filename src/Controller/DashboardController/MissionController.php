<?php
// src/Controller/DashboardController/MissionController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Mission;
use App\Entity\User;
use App\Form\MissionType;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/missions')]
class MissionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionRepository $missionRepository
    ) {
    }

    #[Route('/', name: 'app_admin_missions_list')]
    #[IsGranted('ROLE_RECRUITER')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $missions = $this->missionRepository->findBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $missions,
            'is_admin_view' => false,
        ]);
    }

    #[Route('/create', name: 'app_admin_missions_create')]
    #[IsGranted('ROLE_RECRUITER')]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mission->setUser($user);
            $mission->setCreatedAt(new \DateTime());
            $mission->setCreatedById($user->getId());

            $this->entityManager->persist($mission);
            $this->entityManager->flush();

            $this->addFlash('success', 'La mission a été créée avec succès.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/create.html.twig', [
            'form' => $form->createView(),
            'mission' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_missions_edit')]
    #[IsGranted('ROLE_RECRUITER')]
    public function edit(Request $request, Mission $mission): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que l'utilisateur est bien le propriétaire de la mission
        if ($mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        // Créer le formulaire avec les données existantes
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        // Vérifier la soumission et la validation
        if ($form->isSubmitted() && $form->isValid()) {
            // Les données sont valides, on sauvegarde
            $this->entityManager->flush();
            $this->addFlash('success', 'La mission a été modifiée avec succès.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        // Si le formulaire est invalide, on retourne les erreurs
        return $this->render('BackOffice/dashboard/missions/edit.html.twig', [
            'form' => $form->createView(),
            'mission' => $mission,
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_missions_show')]
    #[IsGranted('ROLE_RECRUITER')]
    public function show(Mission $mission): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que l'utilisateur a le droit de voir cette mission
        if ($mission->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Vous ne pouvez pas voir cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/show.html.twig', [
            'mission' => $mission,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_missions_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function delete(Request $request, Mission $mission): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que l'utilisateur est bien le propriétaire de la mission
        if ($mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        if ($this->isCsrfTokenValid('delete' . $mission->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($mission);
            $this->entityManager->flush();
            $this->addFlash('success', 'La mission a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_missions_list');
    }
}