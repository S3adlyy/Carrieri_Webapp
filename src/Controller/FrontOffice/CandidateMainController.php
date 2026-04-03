<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function offres(): Response
    {
        return $this->render('FrontOffice/main/offres.html.twig');
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
}
