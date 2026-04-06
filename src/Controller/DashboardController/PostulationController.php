<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Repository\PostulationRepository;
use App\Repository\OffreEmploiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin/postulations')]
class PostulationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostulationRepository $postulationRepository,
        private OffreEmploiRepository $offreEmploiRepository,
    ) {
    }

    #[Route('/', name: 'app_admin_postulations_list')]
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
            'statut' => $request->query->get('statut', ''),
            'offreId' => $request->query->get('offreId', ''),
            'typeContrat' => $request->query->get('typeContrat', ''),
            'dateDebut' => $request->query->get('dateDebut', ''),
            'dateFin' => $request->query->get('dateFin', ''),
        ];

        // Utiliser la nouvelle méthode de recherche avancée
        $postulations = $this->postulationRepository->searchPostulationsWithFilters($user, $filters);
        $stats = $this->getStats($user);

        // Liste des offres pour le filtre
        $offres = $this->offreEmploiRepository->findByUserWithSearch($user, '');

        return $this->render('BackOffice/dashboard/postulations/index.html.twig', [
            'postulations' => $postulations,
            'stats' => $stats,
            'filters' => $filters,
            'offres' => $offres,
            'is_admin_view' => in_array('ROLE_ADMIN', $user->getRoles()),
        ]);
    }

    #[Route('/offre/{id}', name: 'app_admin_postulations_by_offre')]
    #[IsGranted('ROLE_RECRUITER')]
    public function byOffre(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $offre = $this->offreEmploiRepository->find($id);
        if (!$offre || ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles()))) {
            $this->addFlash('error', 'Offre non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        // Récupérer les filtres pour la page par offre
        $filters = [
            'keyword' => $request->query->get('keyword', ''),
            'statut' => $request->query->get('statut', ''),
            'dateDebut' => $request->query->get('dateDebut', ''),
            'dateFin' => $request->query->get('dateFin', ''),
            'offreId' => $id,
        ];

        $postulations = $this->postulationRepository->searchPostulationsWithFilters($user, $filters);

        return $this->render('BackOffice/dashboard/postulations/by_offre.html.twig', [
            'postulations' => $postulations,
            'offre' => $offre,
            'filters' => $filters,
            'is_admin_view' => in_array('ROLE_ADMIN', $user->getRoles()),
        ]);
    }

    #[Route('/{id}/statut', name: 'app_admin_postulations_update_statut', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function updateStatut(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $postulation = $this->postulationRepository->find($id);
        if (!$postulation) {
            $this->addFlash('error', 'Postulation non trouvée.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        // Make sure the recruiter owns the offer
        $offre = $postulation->getOffreEmploi();
        if (!$offre || ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles()))) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        $statut = $request->request->get('statut');
        $allowedStatuts = ['En attente', 'Acceptée', 'Refusée'];

        if (!in_array($statut, $allowedStatuts)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        if (!$this->isCsrfTokenValid('statut' . $postulation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        $postulation->setStatut($statut);
        $this->entityManager->flush();

        $this->addFlash('success', 'Statut mis à jour avec succès.');

        // Redirect back to the offer's postulations
        return $this->redirectToRoute('app_admin_postulations_by_offre', ['id' => $offre->getId()]);
    }

    private function getStats(User $user): array
    {
        $postulations = $this->postulationRepository->findByRecruiter($user);
        $total = count($postulations);
        $accepted = 0;
        $refused = 0;
        $pending = 0;

        foreach ($postulations as $p) {
            match ($p->getStatut()) {
                'Acceptée' => $accepted++,
                'Refusée' => $refused++,
                default => $pending++,
            };
        }

        return [
            'total' => $total,
            'accepted' => $accepted,
            'refused' => $refused,
            'pending' => $pending,
        ];
    }

    #[Route('/offre/{id}/export-pdf', name: 'app_admin_postulations_export_pdf')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportPdf(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $offre = $this->offreEmploiRepository->find($id);
        if (!$offre || ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles()))) {
            $this->addFlash('error', 'Offre non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        $postulations = $this->postulationRepository->findByOffreAndRecruiter($id, $user);

        $html = $this->renderView('BackOffice/dashboard/postulations/export_pdf.html.twig', [
            'postulations' => $postulations,
            'offre' => $offre,
            'export_date' => date('d/m/Y H:i:s'),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fileName = 'postulations_' . $offre->getId() . '_' . date('Y-m-d') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_postulations_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $postulation = $this->postulationRepository->find($id);
        if (!$postulation) {
            $this->addFlash('error', 'Postulation non trouvée.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        // Vérifier que le recruteur possède l'offre associée
        $offre = $postulation->getOffreEmploi();
        if (!$offre || ($offre->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles()))) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete_postulation_' . $postulation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_postulations_list');
        }

        // Supprimer le fichier CV s'il existe
        $cvPath = $postulation->getCvPath();
        if ($cvPath) {
            $fullPath = $this->getParameter('kernel.project_dir') . '/public/' . $cvPath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $this->entityManager->remove($postulation);
        $this->entityManager->flush();

        $this->addFlash('success', 'La candidature a été supprimée avec succès.');

        // Rediriger vers la page précédente si possible
        $referer = $request->headers->get('referer');
        if ($referer && strpos($referer, 'app_admin_postulations_by_offre') !== false) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_admin_postulations_list');
    }
}