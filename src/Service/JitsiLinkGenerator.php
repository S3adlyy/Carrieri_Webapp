<?php
// src/Service/JitsiLinkGenerator.php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JitsiLinkGenerator
{
    private string $jitsiDomain;
    private string $roomPrefix;

    public function __construct(ParameterBagInterface $params)
    {
        $this->jitsiDomain = $params->get('jitsi_domain');
        $this->roomPrefix = $params->get('jitsi_room_prefix');
    }

    /**
     * Génère un lien de salle Jitsi unique pour l'entretien
     */
    public function generateMeetingLink(int $entretienId, int $candidatId, int $recruiterId): string
    {
        // Générer un nom de salon unique
        $roomName = sprintf(
            '%s_entretien_%d_candidat_%d_recruteur_%d_%s',
            $this->roomPrefix,
            $entretienId,
            $candidatId,
            $recruiterId,
            substr(md5(uniqid()), 0, 8)
        );

        // Nettoyer le nom de la salle (pas d'espaces, pas de caractères spéciaux)
        $roomName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $roomName);

        // Construire l'URL Jitsi complète
        $jitsiUrl = sprintf(
            'https://%s/%s',
            $this->jitsiDomain,
            $roomName
        );

        return $jitsiUrl;
    }

    /**
     * Génère un nom de salle simple pour test
     */
    public function generateTestRoom(string $suffix = 'test'): string
    {
        $roomName = sprintf(
            '%s_%s_%s',
            $this->roomPrefix,
            $suffix,
            date('Ymd_His')
        );

        return sprintf('https://%s/%s', $this->jitsiDomain, $roomName);
    }
}