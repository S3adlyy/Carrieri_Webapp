<?php

namespace App\Service;

use App\Repository\ReclamationRepository;
use App\Repository\FeedbackRepository;

class ExportService
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Exporter les feedbacks en CSV formaté
     */
    public function exportFeedbacksToCsv(): string
    {
        $feedbacks = $this->feedbackRepository->findAll();
        
        $filename = tempnam(sys_get_temp_dir(), 'feedbacks_') . '.csv';
        $file = fopen($filename, 'w');
        
        // BOM UTF-8 pour les accents
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Séparateur point-virgule (meilleur pour Excel)
        $separateur = ';';
        
        // En-têtes
        fputcsv($file, [
            'ID', 
            'Commentaire', 
            'Note', 
            'Mission', 
            'Candidat', 
            'Date création'
        ], $separateur);
        
        // Données formatées
        foreach ($feedbacks as $f) {
            $candidat = $f->getRenduMission()?->getUser();
            $nomCandidat = $candidat ? trim($candidat->getFirstName() . ' ' . $candidat->getLastName()) : '—';
            
            // Note corrigée (entre 1 et 5)
            $note = $f->getNote();
            $note = max(1, min(5, $note));
            $etoiles = $this->getStars($note);
            
            fputcsv($file, [
                $f->getId(),
                $f->getCommentaire(),
                $etoiles . ' (' . $note . '/5)',
                $f->getRenduMission() ? 'Mission #' . $f->getRenduMission()->getId() : '—',
                $nomCandidat,
                $f->getCreatedAt()?->format('d/m/Y H:i:s')
            ], $separateur);
        }
        
        fclose($file);
        return $filename;
    }

    /**
     * Exporter en HTML (peut être ouvert dans Excel)
     */
    public function exportFeedbacksToHtml(): string
    {
        $feedbacks = $this->feedbackRepository->findAll();
        
        $filename = tempnam(sys_get_temp_dir(), 'feedbacks_') . '.xls';
        $file = fopen($filename, 'w');
        
        // Début du HTML
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Rapport Feedbacks</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #4472C4; text-align: center; }
                table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                th { background-color: #4472C4; color: white; padding: 10px; border: 1px solid #ddd; }
                td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                tr:nth-child(even) { background-color: #f2f2f2; }
                .note-bonne { color: green; font-weight: bold; }
                .note-moyenne { color: orange; font-weight: bold; }
                .note-mauvaise { color: red; font-weight: bold; }
                .etoile { color: gold; }
            </style>
        </head>
        <body>
            <h1>📊 Rapport des Feedbacks</h1>
            <p>Généré le : ' . date('d/m/Y H:i:s') . '</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Commentaire</th>
                        <th>Note</th>
                        <th>Mission</th>
                        <th>Candidat</th>
                        <th>Date création</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($feedbacks as $f) {
            $candidat = $f->getRenduMission()?->getUser();
            $nomCandidat = $candidat ? trim($candidat->getFirstName() . ' ' . $candidat->getLastName()) : '—';
            
            $note = $f->getNote();
            $note = max(1, min(5, $note));
            $etoiles = $this->getStarsHtml($note);
            $classeNote = $this->getNoteClass($note);
            
            $html .= '<tr>
                <td>' . $f->getId() . '</td>
                <td>' . htmlspecialchars($f->getCommentaire()) . '</td>
                <td class="' . $classeNote . '">' . $etoiles . ' (' . $note . '/5)</td>
                <td>' . ($f->getRenduMission() ? 'Mission #' . $f->getRenduMission()->getId() : '—') . '</td>
                <td>' . htmlspecialchars($nomCandidat) . '</td>
                <td>' . ($f->getCreatedAt()?->format('d/m/Y H:i:s')) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        fwrite($file, $html);
        fclose($file);
        
        return $filename;
    }

    /**
     * Exporter les réclamations en CSV formaté
     */
    public function exportReclamationsToCsv(): string
    {
        $reclamations = $this->reclamationRepository->findAll();
        
        $filename = tempnam(sys_get_temp_dir(), 'reclamations_') . '.csv';
        $file = fopen($filename, 'w');
        
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        $separateur = ';';
        
        fputcsv($file, [
            'ID', 'Objet', 'Description', 'Catégorie', 'Statut', 'Priorité', 'Cours', 'Candidat', 'Email', 'Date création'
        ], $separateur);
        
        foreach ($reclamations as $r) {
            $candidat = $r->getUser();
            $nomCandidat = $candidat ? trim($candidat->getFirstName() . ' ' . $candidat->getLastName()) : '—';
            
            fputcsv($file, [
                $r->getId(),
                $r->getObjet(),
                $r->getDescription(),
                $r->getCategorie(),
                $this->getStatusText($r->getStatut()),
                $this->getPriorityText($r->getPriorite()),
                $r->getCours()?->getTitre() ?: '—',
                $nomCandidat,
                $r->getEmail(),
                $r->getDateCreation()?->format('d/m/Y H:i:s')
            ], $separateur);
        }
        
        fclose($file);
        return $filename;
    }

    // ========== MÉTHODES PRIVÉES ==========

    private function getStars(int $note): string
    {
        // Protection contre les notes invalides
        if ($note < 0) $note = 0;
        if ($note > 5) $note = 5;
        
        return str_repeat('★', $note) . str_repeat('☆', 5 - $note);
    }

    private function getStarsHtml(int $note): string
    {
        // Protection contre les notes invalides
        if ($note < 0) $note = 0;
        if ($note > 5) $note = 5;
        
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $note) {
                $stars .= '<span class="etoile">★</span>';
            } else {
                $stars .= '<span class="etoile">☆</span>';
            }
        }
        return $stars;
    }

    private function getNoteClass(int $note): string
    {
        if ($note >= 4) return 'note-bonne';
        if ($note >= 2) return 'note-moyenne';
        return 'note-mauvaise';
    }

    private function getStatusText(?string $statut): string
    {
        return match($statut) {
            'Traité' => '✓ Traité',
            'En attente' => '⏳ En attente',
            'En cours' => '🔄 En cours',
            default => $statut ?: '—'
        };
    }

    private function getPriorityText(?string $priorite): string
    {
        return match($priorite) {
            'Haute' => '🔴 Haute',
            'Moyenne' => '🟠 Moyenne',
            'Basse' => '🟢 Basse',
            default => $priorite ?: '—'
        };
    }
}