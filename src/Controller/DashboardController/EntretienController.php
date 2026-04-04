<?php
// src/Controller/DashboardController/EntretienController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Entretien;
use App\Entity\User;
use App\Form\EntretienType;
use App\Repository\RenduMissionRepository;
use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/entretiens')]
#[IsGranted('ROLE_RECRUITER')]
class EntretienController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RenduMissionRepository $renduMissionRepository,
        private EntretienRepository $entretienRepository
    ) {
    }

    #[Route('/candidats-acceptes', name: 'app_admin_candidats_acceptes')]
    public function candidatsAcceptes(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer tous les rendus acceptés pour les missions du recruteur
        $candidatsAcceptes = $this->renduMissionRepository->findAcceptedSubmissionsByRecruiter($user->getId());

        return $this->render('BackOffice/dashboard/entretiens/candidats_acceptes.html.twig', [
            'candidats' => $candidatsAcceptes,
        ]);
    }

    #[Route('/creer/{renduId}', name: 'app_admin_entretien_create')]
    public function create(int $renduId, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rendu = $this->renduMissionRepository->find($renduId);
        if (!$rendu || $rendu->getStatut() !== 'accepte') {
            $this->addFlash('error', 'Soumission non trouvée ou non acceptée.');
            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        $entretien = new Entretien();
        $entretien->setRendu($rendu);
        $entretien->setStatus('planifie');

        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entretien);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Entretien planifié pour %s le %s',
                $rendu->getUser()->getEmail(),
                $entretien->getDateEntretien()->format('d/m/Y à H:i')
            ));

            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        return $this->render('BackOffice/dashboard/entretiens/create.html.twig', [
            'form' => $form->createView(),
            'rendu' => $rendu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_entretien_edit')]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $entretien = $this->entretienRepository->find($id);
        if (!$entretien) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Entretien modifié avec succès.');
            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        return $this->render('BackOffice/dashboard/entretiens/edit.html.twig', [
            'form' => $form->createView(),
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_entretien_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $entretien = $this->entretienRepository->find($id);
        if (!$entretien) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        if ($this->isCsrfTokenValid('delete' . $entretien->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($entretien);
            $this->entityManager->flush();
            $this->addFlash('success', 'Entretien supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_candidats_acceptes');
    }
}