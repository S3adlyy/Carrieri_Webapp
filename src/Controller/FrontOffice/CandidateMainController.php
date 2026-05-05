<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\OffreEmploi;
use App\Service\CandidateOfferMatchService;
use App\Entity\Postulation;
use App\Repository\FavoritesOffresRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PostulationRepository;
use App\Repository\MissionRepository;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Psr\Log\LoggerInterface;
use App\Entity\User;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CandidateMainController extends AbstractController
{
    use UserTypeCasterTrait;
    private LoggerInterface $logger;
    private ProfileService $profileService;

    public function __construct(ProfileService $profileService, LoggerInterface $logger)
    {
        $this->profileService = $profileService;
        $this->logger = $logger;
    }

    #[Route('', name: 'app_candidate_main')]
    public function main(): Response
    {
        return $this->render('FrontOffice/main/main.html.twig');
    }


    #[Route('/offres', name: 'app_candidate_offres')]
    public function offres(
        Request $request,
        OffreEmploiRepository $offreEmploiRepository,
        FavoritesOffresRepository $favoritesRepo,
        CandidateOfferMatchService $candidateOfferMatchService
    ): Response {
        $keyword = $this->queryString($request, 'keyword');
        $type = $this->queryString($request, 'type');
        $localisation = $this->queryString($request, 'localisation');
        $salaireMin = $request->query->get('salaire');
        $sort = (string) $request->query->get('sort', 'smart');

        $offres = $offreEmploiRepository->searchAndFilter(
            $keyword,
            $type,
            $localisation,
            $salaireMin ? (float) $salaireMin : null
        );

        $allOffres = $offreEmploiRepository->findActiveOffers();

        $stats = [
            'total' => count($allOffres),
            'CDI' => count(array_filter($allOffres, fn($o) => $o->getTypeContrat() === 'CDI')),
            'CDD' => count(array_filter($allOffres, fn($o) => $o->getTypeContrat() === 'CDD')),
            'Stage' => count(array_filter($allOffres, fn($o) => $o->getTypeContrat() === 'Stage')),
            'Freelance' => count(array_filter($allOffres, fn($o) => $o->getTypeContrat() === 'Freelance')),
        ];

        $user = $this->getUser();
        $favoriteIds = [];
        $rankedOffres = [];

        if ($user instanceof User) {
            $candidateId = $this->requireEntityId($user->getId());
            $favoriteIds = $favoritesRepo->getFavoriteOfferIdsByCandidat($candidateId);
            $rankedOffres = $this->buildRankedOffers($offres, $user, $candidateOfferMatchService);

            if ($sort === 'date') {
                usort($rankedOffres, static function (array $left, array $right): int {
                    $leftDate = $left['offre']->getDatePublication()?->getTimestamp() ?? 0;
                    $rightDate = $right['offre']->getDatePublication()?->getTimestamp() ?? 0;

                    return $rightDate <=> $leftDate;
                });
            } else {
                usort($rankedOffres, static function (array $left, array $right): int {
                    return $right['match']['score'] <=> $left['match']['score'];
                });
            }
        } else {
            foreach ($offres as $offre) {
                $rankedOffres[] = [
                    'offre' => $offre,
                    'match' => null,
                ];
            }
        }

        return $this->render('FrontOffice/main/offres.html.twig', [
            'rankedOffres' => $rankedOffres,
            'stats' => $stats,
            'favoriteIds' => $favoriteIds,
            'currentSort' => $sort,
        ]);
    }

    #[Route('/offres/smart', name: 'app_candidate_offres_smart')]
    public function smartOffres(
        Request $request,
        OffreEmploiRepository $offreEmploiRepository,
        FavoritesOffresRepository $favoritesRepo,
        CandidateOfferMatchService $candidateOfferMatchService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $offres = $offreEmploiRepository->searchAndFilter(
            $this->queryString($request, 'keyword'),
            $this->queryString($request, 'type'),
            $this->queryString($request, 'localisation'),
            $request->query->get('salaire') ? (float) $request->query->get('salaire') : null
        );

        $rankedOffres = $this->buildRankedOffers($offres, $user, $candidateOfferMatchService);
        usort($rankedOffres, static function (array $left, array $right): int {
            return $right['match']['score'] <=> $left['match']['score'];
        });

        return $this->render('FrontOffice/main/offres_smart.html.twig', [
            'rankedOffres' => $rankedOffres,
            'favoriteIds' => $favoritesRepo->getFavoriteOfferIdsByCandidat($this->requireEntityId($user->getId())),
        ]);
    }

    #[Route('/offres/{id}', name: 'app_candidate_offre_show', requirements: ['id' => '\d+'])]
    public function showOffre(
        OffreEmploi $offre,
        MissionRepository $missionRepository,
        PostulationRepository $postulationRepository,
        FavoritesOffresRepository $favoritesRepo
    ): Response {
        if ($offre->getDateExpiration() && $offre->getDateExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Cette offre n\'est plus disponible.');
        }

        $missions = [];
        if ($offre->getRecruteurId() !== null) {
            $missions = $missionRepository->findByCreatedById($offre->getRecruteurId());
        }

        $alreadyApplied = false;
        $postulationStatus = null;
        $isFavorite = false;

        $user = $this->getUser();
        if ($user instanceof User) {
            $alreadyApplied = $postulationRepository->hasUserAppliedToOffer($user, $offre);
            $isFavorite = $favoritesRepo->isFavorite(
                $this->requireEntityId($user->getId()),
                $this->requireEntityId($offre->getId())
            );

            if ($alreadyApplied) {
                $postulation = $postulationRepository->findOneBy([
                    'user' => $user,
                    'offreEmploi' => $offre,
                ]);
                $postulationStatus = $postulation ? $postulation->getStatut() : null;
            }
        }

        $joursRestants = null;
        if ($offre->getDateExpiration()) {
            $today = new \DateTime();
            $interval = $today->diff($offre->getDateExpiration());
            $joursRestants = max(0, (int) $interval->format('%r%a'));
        }

        return $this->render('FrontOffice/main/offre_show.html.twig', [
            'offre' => $offre,
            'missions' => $missions,
            'alreadyApplied' => $alreadyApplied,
            'postulationStatus' => $postulationStatus,
            'joursRestants' => $joursRestants,
            'missionsCount' => count($missions),
            'isFavorite' => $isFavorite,
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
        // Rediriger vers le vrai contrôleur des réclamations
        return $this->redirectToRoute('app_candidat_reclamation_index');
    }

    //nahineha khater ken nkhaliwha twali mochkla f routes w tekhdemch
    /*#[Route('/messagerie', name: 'app_candidate_messagerie')]
    public function messagerie(): Response
    {
        return $this->render('FrontOffice/main/messagerie.html.twig');
    }*/
    #[Route('/offres/{id}/postuler', name: 'app_candidate_offre_apply', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function applyOffre(
        OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
        PostulationRepository $postulationRepository,
        SmsService $smsService
    ): Response {
        if ($offre->getDateExpiration() && $offre->getDateExpiration() < new \DateTime()) {
            throw $this->createNotFoundException('Cette offre n\'est plus disponible.');
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

            if ($motivation === '') {
                $errors[] = 'La motivation est obligatoire.';
            } elseif (mb_strlen($motivation) < 20) {
                $errors[] = 'La motivation doit contenir au moins 20 caractères.';
            } elseif (mb_strlen($motivation) > 2000) {
                $errors[] = 'La motivation ne doit pas dépasser 2000 caractères.';
            }

            if (!$cvFile) {
                $errors[] = 'Le CV est obligatoire.';
            } else {
                $allowedExtensions = ['pdf', 'doc', 'docx'];
                $originalName = $cvFile->getClientOriginalName();
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $errors[] = 'Le CV doit être au format PDF, DOC ou DOCX.';
                }

                if ($cvFile->getSize() > 2 * 1024 * 1024) {
                    $errors[] = 'Le CV ne doit pas dépasser 2 Mo.';
                }
            }

            if ($errors === []) {
                $projectDir = $this->getParameter('kernel.project_dir');
                if (!is_string($projectDir)) {
                    throw new \LogicException('The kernel.project_dir parameter must be a string.');
                }

                $uploadDir = $projectDir . '/public/uploads/cv';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeFilename = uniqid('cv_', true) . '.' . $cvFile->guessExtension();

                try {
                    $cvFile->move($uploadDir, $safeFilename);
                } catch (FileException $e) {
                    $errors[] = 'Erreur lors de l\'envoi du CV.';
                }

                if ($errors === []) {
                    $postulation = new Postulation();
                    $postulation->setDatePostulation(new \DateTime());
                    $postulation->setStatut('En attente');
                    $postulation->setMotivationCandidature($motivation);
                    $postulation->setCvPath('uploads/cv/' . $safeFilename);
                    $postulation->setCandidatId($this->requireEntityId($user->getId()));
                    $postulation->setOffreId($this->requireEntityId($offre->getId()));
                    $postulation->setUser($user);
                    $postulation->setOffreEmploi($offre);

                    $entityManager->persist($postulation);
                    $entityManager->flush();

                    // ==================== SMS NOTIFICATION ====================
                    $phone = $user->getPhone();

                    $this->logger->info('=== SMS DEBUG START ===', [
                        'user_id' => $user->getId(),
                        'phone_from_db' => $phone,
                        'phone_not_empty' => !empty($phone),
                        'offer_title' => $offre->getTitre()
                    ]);

                    if (!empty($phone)) {
                        $smsMessage = "Candidature envoyée avec succès !\n" .
                            "Offre: {$offre->getTitre()}\n" .
                            "Vous serez contacté bientôt.\n\nCarrieri";
                        $this->logger->info('Calling sendSms method', ['to' => $phone]);

                        $sent = $smsService->sendSms($phone, $smsMessage);

                        if ($sent) {
                            $this->logger->info('SMS returned true - should be sent');
                        } else {
                            $this->logger->error('SMS returned false - failed');
                        }
                    } else {
                        $this->logger->warning('No phone number found for user');
                    }

                    $this->logger->info('=== SMS DEBUG END ===');
                    // =========================================================

                    $this->addFlash('success', 'Votre candidature a été envoyée avec succès.');
                    return $this->redirectToRoute('app_candidate_postulations');
                }
            }
        }

        return $this->render('FrontOffice/main/offre_apply.html.twig', [
            'offre' => $offre,
            'errors' => $errors,
            'motivation' => $motivation,
        ]);
    }


    #[Route('/mes-postulations', name: 'app_candidate_postulations')]
    public function myApplications(Request $request, PostulationRepository $postulationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $filters = [
            'keyword' => $request->query->get('keyword'),
            'statut'  => $request->query->get('statut'),
            'offre'   => $request->query->get('offre'),
        ];

        $postulations = $postulationRepository->searchPostulationsForCandidate($user, $filters);
        $stats = $postulationRepository->getStatsByUser($user);

        return $this->render('FrontOffice/main/postulations.html.twig', [
            'postulations' => $postulations,
            'stats' => $stats,
        ]);
    }

    #[Route('/profile', name: 'app_candidate_profile')]  // Add this route attribute
    public function profile(): Response
    {
        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($user, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
        ]);
    }
    #[Route('/favorites', name: 'app_candidate_favorites')]
    public function favorites(
        FavoritesOffresRepository $favoritesRepo,
        OffreEmploiRepository $offreEmploiRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $favoritesRows = $favoritesRepo->getFavoritesByCandidat($this->requireEntityId($user->getId()));
        $favorites = [];

        foreach ($favoritesRows as $favoriteRow) {
            $offreId = isset($favoriteRow['offre_id']) ? (int) $favoriteRow['offre_id'] : null;
            if (!$offreId) {
                continue;
            }

            $offre = $offreEmploiRepository->find($offreId);

            if (!$offre) {
                continue;
            }

            $dateAjout = null;
            if (!empty($favoriteRow['date_ajout']) && is_string($favoriteRow['date_ajout'])) {
                try {
                    $dateAjout = new \DateTimeImmutable($favoriteRow['date_ajout']);
                } catch (\Exception) {
                    $dateAjout = null;
                }
            }

            $favorites[] = [
                'id' => isset($favoriteRow['id']) ? (int) $favoriteRow['id'] : null,
                'dateAjout' => $dateAjout,
                'offre' => $offre,
            ];
        }

        return $this->render('FrontOffice/main/favorites.html.twig', [
            'favorites' => $favorites,
        ]);
    }
    #[Route('/offre/{id}/toggle-favorite', name: 'app_candidate_favorite_toggle', methods: ['POST'])]
    public function toggleFavorite(
        int $id,
        Request $request,
        FavoritesOffresRepository $favoritesRepo
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $candidateId = $this->requireEntityId($user->getId());
        $isFavorite = $favoritesRepo->isFavorite($candidateId, $id);

        if ($isFavorite && $request->request->get('_favorite_action') === 'add') {
            $success = true;
            $message = 'Offre déjà dans vos favoris.';
        } elseif ($isFavorite) {
            $success = $favoritesRepo->removeFavorite($candidateId, $id);
            $message = $success ? 'Offre retirée des favoris.' : 'Impossible de retirer cette offre des favoris.';
        } else {
            $success = $favoritesRepo->addFavorite($candidateId, $id);
            $message = $success ? 'Offre ajoutée aux favoris.' : 'Impossible d’ajouter cette offre aux favoris.';
        }

        if ($request->isXmlHttpRequest()) {
            return new Response('', $success ? Response::HTTP_NO_CONTENT : Response::HTTP_BAD_REQUEST);
        }

        $this->addFlash($success ? 'success' : 'error', $message);

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_candidate_offres'));
    }

    /**
     * @param OffreEmploi[] $offres
     * @return array<int, array{offre: OffreEmploi, match: array<string, mixed>}>
     */
    private function buildRankedOffers(
        array $offres,
        User $user,
        CandidateOfferMatchService $candidateOfferMatchService
    ): array {
        $rankedOffres = [];

        foreach ($offres as $offre) {
            $rankedOffres[] = [
                'offre' => $offre,
                'match' => $candidateOfferMatchService->match($user, $offre),
            ];
        }

        return $rankedOffres;
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function requireEntityId(?int $id): int
    {
        if ($id === null) {
            throw new \LogicException('Expected a persisted entity with an id.');
        }

        return $id;
    }
}
