<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\OffreEmploi;
use App\Entity\User;
use App\Form\OffreEmploiType;
use App\Repository\OffreEmploiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/offres')]
class OffreEmploiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OffreEmploiRepository $offreEmploiRepository,
    ) {
    }

    #[Route('/', name: 'app_admin_offres_list')]
    #[IsGranted('ROLE_RECRUITER')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = $request->query->get('search', '');
        $offres = $this->offreEmploiRepository->findByUserWithSearch($user, $search);

        $stats = $this->getOffreStats($user);

        return $this->render('BackOffice/dashboard/offres_emploi/index.html.twig', [
            'offres' => $offres,
            'is_admin_view' => in_array('ROLE_ADMIN', $user->getRoles()),
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    #[Route('/create', name: 'app_admin_offres_create')]
    #[IsGranted('ROLE_RECRUITER')]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $offre = new OffreEmploi();
        $form = $this->createForm(OffreEmploiType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offre->setDatePublication(new \DateTime());
            $offre->setUser($user);
            $offre->setRecruteurId($user->getId());

            $this->entityManager->persist($offre);
            $this->entityManager->flush();

            $this->addFlash('success', "L'offre d'emploi a été créée avec succès.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        return $this->render('BackOffice/dashboard/offres_emploi/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_offres_edit')]
    #[IsGranted('ROLE_RECRUITER')]
    public function edit(Request $request, OffreEmploi $offre): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', "Vous ne pouvez pas modifier cette offre.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        $form = $this->createForm(OffreEmploiType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', "L'offre a été modifiée avec succès.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        return $this->render('BackOffice/dashboard/offres_emploi/edit.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_offres_show')]
    #[IsGranted('ROLE_RECRUITER')]
    public function show(OffreEmploi $offre): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', "Vous ne pouvez pas voir cette offre.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        return $this->render('BackOffice/dashboard/offres_emploi/show.html.twig', [
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_offres_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function delete(Request $request, OffreEmploi $offre): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', "Vous ne pouvez pas supprimer cette offre.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        if ($this->isCsrfTokenValid('delete' . $offre->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($offre);
            $this->entityManager->flush();
            $this->addFlash('success', "L'offre a été supprimée avec succès.");
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_offres_list');
    }

    private function getOffreStats(User $user): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());
        $offres = $isAdmin
            ? $this->offreEmploiRepository->findBy([], ['id' => 'DESC'])
            : $this->offreEmploiRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $total = count($offres);
        $actives = 0;
        $expirees = 0;
        $today = new \DateTime();

        foreach ($offres as $offre) {
            if ($offre->getDateExpiration() && $offre->getDateExpiration() > $today) {
                $actives++;
            } else {
                $expirees++;
            }
        }

        $parContrat = ['CDI' => 0, 'CDD' => 0, 'Stage' => 0, 'Freelance' => 0];
        foreach ($offres as $offre) {
            $type = $offre->getTypeContrat();
            if ($type && isset($parContrat[$type])) {
                $parContrat[$type]++;
            }
        }

        return [
            'total' => $total,
            'actives' => $actives,
            'expirees' => $expirees,
            'par_contrat' => $parContrat,
        ];
    }
}