<?php

namespace App\Service;

use App\Entity\AchatCours;
use App\Entity\Cours;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;

class PaiementService
{
    private EntityManagerInterface $entityManager;
    private string $stripeSecretKey;
    private string $stripePublicKey;

    public function __construct(
        EntityManagerInterface $entityManager,
        string $stripeSecretKey,
        string $stripePublicKey
    ) {
        $this->entityManager = $entityManager;
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripePublicKey = $stripePublicKey;
        
        $this->assertStripeConfigured();
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée un PaymentIntent Stripe
     */
    public function creerPaymentIntent(
        float $montant,
        string $titre,
        int $coursId,
        int $candidatId
    ): string {
        try {
            $intent = PaymentIntent::create([
                'amount' => (int)($montant * 100), // Convertir en centimes
                'currency' => 'eur',
                'metadata' => [
                    'cours_id' => (string)$coursId,
                    'candidat_id' => (string)$candidatId,
                    'titre' => $titre,
                ]
            ]);

            return $intent->client_secret;
        } catch (ApiErrorException $e) {
            throw new \Exception('Erreur Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Confirme le paiement et crée l'enregistrement AchatCours
     */
    public function confirmerPaiement(
        int $candidatId,
        int $coursId,
        string $paymentIntentId,
        float $montant
    ): AchatCours {
        $existingIntent = $this->entityManager
            ->getRepository(AchatCours::class)
            ->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);
        if ($existingIntent instanceof AchatCours) {
            return $existingIntent;
        }

        $existingAccess = $this->entityManager
            ->getRepository(AchatCours::class)
            ->findOneBy([
                'candidatId' => $candidatId,
                'coursId' => $coursId,
                'statut' => 'PAYE',
            ]);
        if ($existingAccess instanceof AchatCours) {
            return $existingAccess;
        }

        $achatCours = new AchatCours();
        $achatCours->setCandidatId($candidatId);
        $achatCours->setCoursId($coursId);
        // Keep scalar FK fields and ORM associations in sync for this legacy mapping.
        $achatCours->setUser($this->entityManager->getReference(User::class, $candidatId));
        $achatCours->setCours($this->entityManager->getReference(Cours::class, $coursId));
        $achatCours->setMontant($montant);
        $achatCours->setStripePaymentIntentId($paymentIntentId);
        $achatCours->setStripeSessionId($paymentIntentId);
        $achatCours->setStatut('PAYE');
        $achatCours->setDateAchat(new \DateTime());
        
        // Ajouter une date d'expiration (par exemple, 1 an)
        $dateExpiration = new \DateTime();
        $dateExpiration->modify('+1 year');
        $achatCours->setDateExpiration($dateExpiration);

        $this->entityManager->persist($achatCours);
        $this->entityManager->flush();

        return $achatCours;
    }

    /**
     * Récupère les cours achetés par un candidat
     */
    public function getCoursAchetes(int $candidatId): array
    {
        $achatsCours = $this->entityManager
            ->getRepository(AchatCours::class)
            ->findBy([
                'candidatId' => $candidatId,
                'statut' => 'PAYE'
            ]);

        $coursIds = [];
        foreach ($achatsCours as $achat) {
            $coursIds[] = $achat->getCoursId();
        }

        return $coursIds;
    }

    /**
     * Vérifie si un candidat a accès à un cours
     */
    public function aCcesAuCours(int $candidatId, int $coursId): bool
    {
        $achatCours = $this->entityManager
            ->getRepository(AchatCours::class)
            ->findOneBy([
                'candidatId' => $candidatId,
                'coursId' => $coursId,
                'statut' => 'PAYE'
            ]);

        if (!$achatCours) {
            return false;
        }

        // Vérifier si l'accès n'a pas expiré
        $now = new \DateTime();
        if ($achatCours->getDateExpiration() && $achatCours->getDateExpiration() < $now) {
            return false;
        }

        return true;
    }

    /**
     * Récupère la clé publique Stripe
     */
    public function getPublishableKey(): string
    {
        return $this->stripePublicKey;
    }

    /**
     * Récupère la clé secrète Stripe
     */
    public function getSecretKey(): string
    {
        return $this->stripeSecretKey;
    }

    /**
     * Vérifie le statut d'un PaymentIntent
     */
    public function verifyPaymentIntent(string $paymentIntentId): bool
    {
        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);
            return $intent->status === 'succeeded';
        } catch (ApiErrorException $e) {
            throw new \Exception('Erreur lors de la vérification du paiement: ' . $e->getMessage());
        }
    }

    public function verifyPaymentIntentOwnershipAndAmount(
        string $paymentIntentId,
        int $coursId,
        int $candidatId,
        float $expectedAmount,
        string $expectedCurrency = 'eur'
    ): bool {
        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);

            if ($intent->status !== 'succeeded') {
                return false;
            }

            if ((string) $intent->currency !== strtolower($expectedCurrency)) {
                return false;
            }

            $metadataCoursId = (int) ($intent->metadata->cours_id ?? 0);
            $metadataCandidatId = (int) ($intent->metadata->candidat_id ?? 0);
            if ($metadataCoursId !== $coursId || $metadataCandidatId !== $candidatId) {
                return false;
            }

            $expectedCents = (int) round($expectedAmount * 100);
            $paidCents = (int) ($intent->amount_received ?: $intent->amount);

            return $paidCents === $expectedCents;
        } catch (ApiErrorException $e) {
            throw new \Exception('Erreur lors de la validation Stripe: ' . $e->getMessage());
        }
    }

    private function assertStripeConfigured(): void
    {
        if (
            $this->stripeSecretKey === ''
            || $this->stripePublicKey === ''
            || str_contains($this->stripeSecretKey, 'your_stripe_secret_key_here')
            || str_contains($this->stripePublicKey, 'your_stripe_public_key_here')
        ) {
            throw new \RuntimeException('Clés Stripe non configurées. Mettez STRIPE_PUBLIC_KEY et STRIPE_SECRET_KEY dans .env.local.');
        }
    }
}




