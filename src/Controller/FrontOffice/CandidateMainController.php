<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\OffreEmploi;
use App\Entity\Postulation;
use App\Entity\User;
use App\Repository\OffreEmploiRepository;
use App\Repository\PostulationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CandidateMainController extends AbstractController
{
    #[Route('', name: 'app_candidate_main')]
    public function main(): Response
    {
        return $this->render('FrontOffice/main/main.html.twig');
    }

    #[Route('/cours', name: 'app_candidate_cours')]
    public function cours(): Response
    {
        return $this->render('FrontOffice/main/cours.html.twig');
    }

    #[Route('/offres', name: 'app_candidate_offres')]
    public function offres(OffreEmploiRepository $offreEmploiRepository): Response
    {
        $offres = $offreEmploiRepository->findActiveOffers();

        return $this->render('FrontOffice/main/offres.html.twig', [
            'offres' => $offres,
        ]);
    }

    #[Route('/offres/{id}', name: 'app_candidate_offre_show', requirements: ['id' => '\d+'])]
    public function showOffre(OffreEmploi $offre, MissionRepository $missionRepository): Response
    {
        if ($offre->getDateExpiration() && $offre->getDateExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Cette offre n’est plus disponible.');
        }

        $missions = [];
        if ($offre->getRecruteurId() !== null) {
            $missions = $missionRepository->findByCreatedById($offre->getRecruteurId());
        }

        return $this->render('FrontOffice/main/offre_show.html.twig', [
            'offre' => $offre,
            'missions' => $missions,
        ]);
    }

    #[Route('/mission', name: 'app_candidate_mission')]
    public function mission(): Response
    {
        return $this->render('FrontOffice/main/mission.html.twig');
    }

    #[Route('/reclamation', name: 'app_candidate_reclamation')]
    public function reclamation(): Response
    {
        return $this->render('FrontOffice/main/reclamation.html.twig');
    }

    #[Route('/messagerie', name: 'app_candidate_messagerie')]
    public function messagerie(): Response
    {
        return $this->render('FrontOffice/main/messagerie.html.twig');
    }

    #[Route('/offres/{id}/postuler', name: 'app_candidate_offre_apply', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function applyOffre(
        OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
        PostulationRepository $postulationRepository
    ): Response {
        if ($offre->getDateExpiration() && $offre->getDateExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Cette offre n’est plus disponible.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($postulationRepository->hasUserAppliedToOffer($user, $offre)) {
            $this->addFlash('error', 'Vous avez déjà postulé à cette offre.');

            return $this->redirectToRoute('app_candidate_offres');
        }

        $errors = [];
        $motivation = '';

        if ($request->isMethod('POST')) {
            $motivation = trim((string) $request->request->get('motivation', ''));
            $cvFile = $request->files->get('cv');

            // Contrôle de saisie motivation
            if ($motivation === '') {
                $errors[] = 'La motivation est obligatoire.';
            } elseif (mb_strlen($motivation) < 20) {
                $errors[] = 'La motivation doit contenir au moins 20 caractères.';
            } elseif (mb_strlen($motivation) > 2000) {
                $errors[] = 'La motivation ne doit pas dépasser 2000 caractères.';
            }

            // Contrôle de saisie CV
            if (!$cvFile) {
                $errors[] = 'Le CV est obligatoire.';
            } else {
                $allowedExtensions = ['pdf', 'doc', 'docx'];
                $originalName = $cvFile->getClientOriginalName();
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $errors[] = 'Le CV doit être au format PDF, DOC ou DOCX.';
                }

                // 2 MB max
                if ($cvFile->getSize() > 2 * 1024 * 1024) {
                    $errors[] = 'Le CV ne doit pas dépasser 2 Mo.';
                }
            }

            if ($errors === []) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cv';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeFilename = uniqid('cv_', true) . '.' . $cvFile->guessExtension();

                try {
                    $cvFile->move($uploadDir, $safeFilename);
                } catch (FileException $e) {
                    $errors[] = 'Erreur lors de l’envoi du CV.';
                }

                if ($errors === []) {
                    $postulation = new Postulation();
                    $postulation->setDatePostulation(new \DateTime());
                    $postulation->setStatut('En attente');
                    $postulation->setMotivationCandidature($motivation);
                    $postulation->setCvPath('uploads/cv/' . $safeFilename);
                    $postulation->setCandidatId($user->getId());
                    $postulation->setOffreId($offre->getId());
                    $postulation->setUser($user);
                    $postulation->setOffreEmploi($offre);

                    $entityManager->persist($postulation);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre candidature a été envoyée avec succès.');

                    return $this->redirectToRoute('app_candidate_offres');
                }
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('FrontOffice/main/offre_apply.html.twig', [
            'offre' => $offre,
            'motivation' => $motivation,
        ]);
    }

    #[Route('/mes-postulations', name: 'app_candidate_postulations')]
    public function myApplications(PostulationRepository $postulationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('FrontOffice/main/postulations.html.twig', [
            'postulations' => $postulationRepository->findByCandidate($user),
        ]);
    }

}