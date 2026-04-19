<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\OffreEmploi;
use App\Entity\User;
use App\Form\OffreEmploiType;
use App\Repository\OffreEmploiRepository;
use App\Entity\Postulation;
use App\Service\OffreInsightService;
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
        private OffreInsightService $offreInsightService,
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

        // Récupérer tous les filtres
        $filters = [
            'keyword' => $request->query->get('keyword', ''),
            'typeContrat' => $request->query->get('typeContrat', ''),
            'statut' => $request->query->get('statut', ''),
            'salaireMin' => $request->query->get('salaireMin', ''),
            'dateDebut' => $request->query->get('dateDebut', ''),
            'dateFin' => $request->query->get('dateFin', ''),
        ];

        // Utiliser la nouvelle méthode de recherche avancée
        $offres = $this->offreEmploiRepository->searchOffersWithFilters($user, $filters);
        $stats = $this->getOffreStats($user);

        return $this->render('BackOffice/dashboard/offres_emploi/index.html.twig', [
            'offres' => $offres,
            'is_admin_view' => in_array('ROLE_ADMIN', $user->getRoles()),
            'filters' => $filters,
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

    #[Route('/inline-edit', name: 'app_admin_offres_inline_edit', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function inlineEdit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Non autorisé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['id']) || !isset($data['updates'])) {
            return $this->json(['success' => false, 'error' => 'Données invalides'], 400);
        }

        $offre = $this->offreEmploiRepository->find($data['id']);
        
        if (!$offre) {
            return $this->json(['success' => false, 'error' => 'Offre non trouvée'], 404);
        }

        if ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        // Tous les champs éditables
        $allowedFields = [
            'titre', 'entreprise', 'localisation', 'typeContrat', 'salaire', 
            'niveauQualification', 'experienceRequise', 'competencesRequises', 
            'secteurActivite', 'contactRecruteur', 'dateExpiration'
        ];
        
        foreach ($data['updates'] as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                continue;
            }
            
            $setter = 'set' . ucfirst($field);
            if (method_exists($offre, $setter)) {
                if ($field === 'dateExpiration' && $value) {
                    $offre->$setter(new \DateTime($value));
                } elseif ($field === 'salaire') {
                    $offre->$setter((float) $value);
                } else {
                    $offre->$setter($value);
                }
            }
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/check-unique-titre', name: 'app_admin_offres_check_unique_titre', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function checkUniqueTitre(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $titre = $data['titre'] ?? '';
        $currentId = $data['id'] ?? null;
        
        $qb = $this->offreEmploiRepository->createQueryBuilder('o')
            ->where('o.titre = :titre')
            ->setParameter('titre', $titre);
        
        if ($currentId) {
            $qb->andWhere('o.id != :currentId')
               ->setParameter('currentId', $currentId);
        }
        
        $existing = $qb->getQuery()->getOneOrNullResult();
        
        return $this->json(['valid' => $existing === null]);
    }

    #[Route('/{id}/insights', name: 'app_admin_offres_insights')]
    #[IsGranted('ROLE_RECRUITER')]
    public function insights(OffreEmploi $offre): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', "Vous ne pouvez pas consulter les statistiques de cette offre.");
            return $this->redirectToRoute('app_admin_offres_list');
        }

        /** @var Postulation[] $postulations */
        $postulations = $this->entityManager
            ->getRepository(Postulation::class)
            ->findBy(['offreEmploi' => $offre], ['datePostulation' => 'ASC']);

        $accepted = 0;
        $refused = 0;
        $pending = 0;

        foreach ($postulations as $postulation) {
            match ($postulation->getStatut()) {
                'Acceptée' => $accepted++,
                'Refusée' => $refused++,
                default => $pending++,
            };
        }

        $weeklyMap = [];
        $now = new \DateTimeImmutable('monday this week');

        for ($i = 5; $i >= 0; $i--) {
            $weekStart = $now->modify("-{$i} week");
            $key = $weekStart->format('o-\WW');
            $weeklyMap[$key] = [
                'label' => $weekStart->format('d/m'),
                'count' => 0,
            ];
        }

        foreach ($postulations as $postulation) {
            $date = $postulation->getDatePostulation();
            if (!$date) {
                continue;
            }

            $weekKey = $date->format('o-\WW');
            if (isset($weeklyMap[$weekKey])) {
                $weeklyMap[$weekKey]['count']++;
            }
        }

        $insights = $this->offreInsightService->analyze($offre, $postulations);

        return $this->render('BackOffice/dashboard/offres_emploi/insights.html.twig', [
            'offre' => $offre,
            'postulations' => $postulations,
            'acceptedCount' => $accepted,
            'refusedCount' => $refused,
            'pendingCount' => $pending,
            'weeklyLabels' => array_column($weeklyMap, 'label'),
            'weeklyCounts' => array_column($weeklyMap, 'count'),
            'insights' => $insights,
        ]);
    }

}