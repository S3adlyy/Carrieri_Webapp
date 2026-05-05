<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\CertificateModerationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
final class CandidateFraudNotificationSubscriber
{
    public function __construct(
        private Security $security,
        private CertificateModerationService $certificateModerationService,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = (string) $request->getPathInfo();

        if (!str_starts_with($path, '/candidat')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        if (!in_array('ROLE_CANDIDAT', $user->getRoles(), true)) {
            return;
        }

        $alerts = $this->certificateModerationService->consumePendingFraudPopupsForCandidate((int) $user->getId());
        if ($alerts === []) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session instanceof Session) {
            return;
        }

        foreach ($alerts as $alert) {
            $courseTitle = $alert['course_title'];
            $reason = $alert['reason'];
            $session->getFlashBag()->add(
                'fraud_alert',
                sprintf('Certificat invalide pour "%s". %s', $courseTitle, $reason)
            );
        }
    }
}


