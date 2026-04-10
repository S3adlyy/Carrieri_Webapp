<?php
// src/Controller/DashboardController/EntretienController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Entretien;
use App\Entity\User;
use App\Form\EntretienType;
use App\Repository\MissionRepository;
use App\Repository\RenduMissionRepository;
use App\Repository\EntretienRepository;
use App\Service\JitsiLinkGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;  // ← AJOUTEZ CETTE LIGNE
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/entretiens')]
#[IsGranted('ROLE_RECRUITER')]
class EntretienController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionRepository $missionRepository,
        private RenduMissionRepository $renduMissionRepository,
        private EntretienRepository $entretienRepository,
        private JitsiLinkGenerator $jitsiLinkGenerator,
    ) {
    }

    #[Route('/candidats-acceptes', name: 'app_admin_candidats_acceptes')]
    public function candidatsAcceptes(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

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
            // Générer automatiquement le lien Jitsi
            $jitsiLink = $this->jitsiLinkGenerator->generateMeetingLink(
                $entretien->getId() ?? rand(1000, 9999),
                $rendu->getUser()->getId(),
                $user->getId()
            );

            $entretien->setLien($jitsiLink);

            $this->entityManager->persist($entretien);
            $this->entityManager->flush();

            $candidateName = $rendu->getUser()->getFirstName() . ' ' . $rendu->getUser()->getLastName();

            // 🔔 ENVOYER LA NOTIFICATION D'ENTRETIEN
            $this->sendInterviewNotification(
                $rendu->getUser()->getId(),
                $entretien->getDateEntretien()->format('d/m/Y à H:i'),
                $jitsiLink,
                $entretien->getType(),
                $user->getFirstName() . ' ' . $user->getLastName()
            );

            $this->addFlash('success', sprintf(
                '✅ Entretien planifié pour %s le %s\n🔗 Lien Jitsi : %s\n📨 Notification envoyée au candidat',
                $candidateName,
                $entretien->getDateEntretien()->format('d/m/Y à H:i'),
                $jitsiLink
            ));

            return $this->redirectToRoute('app_admin_candidats_acceptes');
        }

        return $this->render('BackOffice/dashboard/entretiens/create.html.twig', [
            'form' => $form->createView(),
            'rendu' => $rendu,
        ]);
    }

    // Ajoutez cette méthode pour envoyer la notification
    private function sendInterviewNotification(int $candidatId, string $date, string $jitsiLink, string $type, string $recruiterName): void
    {
        try {
            $client = HttpClient::create();
            $client->request('POST', 'http://localhost:3002/api/notify/interview-scheduled', [
                'json' => [
                    'candidatId' => $candidatId,
                    'interviewDate' => $date,
                    'jitsiLink' => $jitsiLink,
                    'interviewType' => $type,
                    'recruiterName' => $recruiterName
                ],
                'timeout' => 5
            ]);
        } catch (\Exception $e) {
            // Ne pas bloquer si la notification échoue
            error_log('Erreur envoi notification: ' . $e->getMessage());
        }
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

    #[Route('/candidats-acceptes/export/excel', name: 'app_admin_candidats_acceptes_export_excel')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportExcel(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $candidats = $this->renduMissionRepository->findAcceptedSubmissionsByRecruiter($user->getId());

        $html = $this->renderView('BackOffice/dashboard/entretiens/export_candidats_excel.html.twig', [
            'candidats' => $candidats,
            'export_date' => date('d/m/Y H:i:s'),
        ]);

        $fileName = 'candidats_acceptes_' . date('Y-m-d_H-i-s') . '.xls';

        return new Response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    #[Route('/candidats-acceptes/export/pdf', name: 'app_admin_candidats_acceptes_export_pdf')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportPDF(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $candidats = $this->renduMissionRepository->findAcceptedSubmissionsByRecruiter($user->getId());

        $html = $this->renderView('BackOffice/dashboard/entretiens/export_candidats_pdf.html.twig', [
            'candidats' => $candidats,
            'export_date' => date('d/m/Y H:i:s'),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fileName = 'candidats_acceptes_' . date('Y-m-d_H-i-s') . '.pdf';
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }

    #[Route('/watch/{missionId}/{candidatId}', name: 'app_recruiter_watch_candidate')]
    public function watchCandidate(int $missionId, int $candidatId): Response
    {
        $recruiter = $this->getUser();
        if (!$recruiter instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = $this->missionRepository->find($missionId);
        if (!$mission || $mission->getUser() !== $recruiter) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette session');
        }

        $candidat = $this->entityManager->getRepository(User::class)->find($candidatId);
        if (!$candidat) {
            throw $this->createNotFoundException('Candidat non trouvé');
        }

        return $this->render('BackOffice/dashboard/entretiens/watch_candidate.html.twig', [
            'mission' => $mission,
            'candidat' => $candidat,
            'missionId' => $missionId,
            'candidatId' => $candidatId
        ]);
    }

    #[Route('/sessions-actives', name: 'app_recruiter_active_sessions')]
    #[IsGranted('ROLE_RECRUITER')]
    public function activeSessions(RenduMissionRepository $renduMissionRepository): Response
    {
        $recruiter = $this->getUser();
        if (!$recruiter instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $missions = $this->missionRepository->findBy(['user' => $recruiter]);
        $missionIds = array_map(fn($m) => $m->getId(), $missions);

        $activeSessions = $renduMissionRepository->findActiveSessionsByMissionIds($missionIds);

        return $this->render('BackOffice/dashboard/entretiens/active_sessions.html.twig', [
            'sessions' => $activeSessions,
        ]);
    }

    #[Route('/active-sessions-count', name: 'app_recruiter_active_sessions_count')]
    #[IsGranted('ROLE_RECRUITER')]
    public function activeSessionsCount(): JsonResponse
    {
        $recruiter = $this->getUser();
        if (!$recruiter instanceof User) {
            return $this->json(['count' => 0]);
        }

        $missions = $this->missionRepository->findBy(['user' => $recruiter]);
        $missionIds = array_map(fn($m) => $m->getId(), $missions);

        $thirtyMinutesAgo = new \DateTime('-30 minutes');

        $activeSessions = $this->renduMissionRepository->createQueryBuilder('r')
            ->where('r.missionId IN (:missionIds)')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.dateRendu >= :thirtyMinutesAgo')
            ->setParameter('missionIds', $missionIds)
            ->setParameter('statut', 'en_attente')
            ->setParameter('thirtyMinutesAgo', $thirtyMinutesAgo)
            ->getQuery()
            ->getResult();

        return $this->json(['count' => count($activeSessions)]);
    }

    #[Route('/live-sessions', name: 'app_recruiter_live_sessions')]
    #[IsGranted('ROLE_RECRUITER')]
    public function liveSessions(): Response
    {
        return $this->render('BackOffice/dashboard/entretiens/live_sessions.html.twig');
    }
}