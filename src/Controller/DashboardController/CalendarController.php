<?php
// src/Controller/DashboardController/CalendarController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/admin/calendrier')]
#[IsGranted('ROLE_RECRUITER')]
class CalendarController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EntretienRepository $entretienRepository,
        private MailerInterface $mailer
    ) {
    }

    #[Route('/', name: 'app_admin_calendar')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('BackOffice/dashboard/calendar/index.html.twig');
    }

    #[Route('/api/events', name: 'app_admin_calendar_events', methods: ['GET'])]
    public function getEvents(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Version plus simple - récupérer tous les entretiens sans filtre de mission d'abord
        $entretiens = $this->entretienRepository->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', 'planifie')
            ->getQuery()
            ->getResult();

        $events = [];
        foreach ($entretiens as $entretien) {
            $rendu = $entretien->getRendu();
            $mission = $rendu ? $rendu->getMission() : null;
            $candidat = $entretien->getCandidat();

            // Vérifier si la mission appartient au recruteur
            if ($mission && $mission->getUser() && $mission->getUser()->getId() !== $user->getId()) {
                continue; // Passer si ce n'est pas la mission du recruteur
            }

            $date = $entretien->getDateEntretien();

            $events[] = [
                'id' => $entretien->getId(),
                'title' => sprintf('%s - %s', $mission ? $mission->getType() : 'N/A', $candidat ? $candidat->getEmail() : 'N/A'),
                'start' => $date->format('Y-m-d\TH:i:s'),
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'extendedProps' => [
                    'candidat' => $candidat ? $candidat->getEmail() : 'N/A',
                    'candidatNom' => $candidat ? ($candidat->getFirstName() . ' ' . $candidat->getLastName()) : 'N/A',
                    'mission' => $mission ? $mission->getType() : 'N/A',
                    'type' => $entretien->getType(),
                    'lien' => $entretien->getLien(),
                ]
            ];
        }

        return $this->json($events);
    }

    #[Route('/api/events/{id}/move', name: 'app_admin_calendar_move', methods: ['PUT'])]
    public function moveEvent(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $entretien = $this->entretienRepository->find($id);
        if (!$entretien) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        $mission = $entretien->getMission();
        if ($mission->getUser() !== $user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $newDate = new \DateTime($data['newDate']);
        $message = $data['message'] ?? '';

        $oldDate = $entretien->getDateEntretien();
        $entretien->setDateEntretien($newDate);

        $this->entityManager->flush();

        // Envoyer une notification au candidat
        $this->sendRescheduleNotification($entretien, $oldDate, $message);

        return $this->json(['success' => true, 'newDate' => $newDate->format('Y-m-d H:i:s')]);
    }

    private function sendRescheduleNotification($entretien, $oldDate, $message): void
    {
        $candidat = $entretien->getCandidat();
        if (!$candidat || !$candidat->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from('noreply@carrieri.com')
            ->to($candidat->getEmail())
            ->subject('Entretien reprogrammé - ' . $entretien->getMission()->getType())
            ->htmlTemplate('emails/entretien_reschedule.html.twig')
            ->context([
                'candidat' => $candidat,
                'entretien' => $entretien,
                'mission' => $entretien->getMission(),
                'oldDate' => $oldDate,
                'message' => $message,
            ]);

        $this->mailer->send($email);
    }
}