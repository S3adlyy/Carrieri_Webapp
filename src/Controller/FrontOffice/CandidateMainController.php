<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CandidateMainController extends AbstractController
{
    private const COURSES_PER_PAGE = 6;

    public function __construct(
        private CoursRepository $coursRepository,
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
    ) {
    }

    #[Route('', name: 'app_candidate_main')]
    public function main(): Response
    {
        return $this->render('FrontOffice/main/main.html.twig');
    }

    #[Route('/cours', name: 'app_candidate_cours')]
    public function cours(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $niveau = trim((string) $request->query->get('niveau', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        $total = $this->coursRepository->countForCandidateFilters($query, $niveau);
        $totalPages = max(1, (int) ceil($total / self::COURSES_PER_PAGE));
        $page = min($page, $totalPages);

        return $this->render('FrontOffice/main/cours.html.twig', [
            'cours_list' => $this->coursRepository->searchForCandidate($query, $niveau, $page, self::COURSES_PER_PAGE),
            'niveaux' => $this->coursRepository->findDistinctNiveaux(),
            'filters' => [
                'q' => $query,
                'niveau' => $niveau,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => self::COURSES_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    #[Route('/cours/{id}', name: 'app_candidate_cours_show', methods: ['GET'])]
    public function coursShow(Cours $cours): Response
    {
        $modules = $this->moduleRepository->findByCours($cours);
        $lessonsByModule = [];

        if ($modules !== []) {
            $moduleIds = array_map(static fn ($m): int => (int) $m->getId(), $modules);
            $lessons = $this->leconRepository->findByModuleIds($moduleIds);

            foreach ($lessons as $lesson) {
                $moduleId = $lesson->getModuleId();
                if ($moduleId === null) {
                    continue;
                }
                $lessonsByModule[$moduleId][] = $lesson;
            }
        }

        return $this->render('FrontOffice/main/cours_show.html.twig', [
            'cours' => $cours,
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
        ]);
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
