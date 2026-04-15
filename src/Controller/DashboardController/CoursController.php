<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Cours;
use App\Entity\User;
use App\Form\CoursType;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Service\BackOfficeDashboardService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cours')]
class CoursController extends AbstractController
{
    public function __construct(
        private BackOfficeDashboardService $dashboardData,
        private CoursRepository $coursRepository,
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'app_admin_cours_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'niveau' => trim((string) $request->query->get('niveau', '')),
        ];

        $coursList = $this->coursRepository->searchForBackOffice(
            $isAdmin ? null : $user,
            $isAdmin,
            $filters['q'],
            $filters['niveau'],
        );

        return $this->render('BackOffice/dashboard/cours/index.html.twig', [
            'cours_list' => $coursList,
            'filters' => $filters,
            'niveaux' => $this->coursRepository->findDistinctNiveauxBackOffice($isAdmin ? null : $user, $isAdmin),
            'is_admin_view' => $isAdmin,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_cours_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();
        $cours = new Cours();
        $form  = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($cours->getCompetencesVisees() === null) {
                $cours->setCompetencesVisees('');
            }


            $imageFile = $form->get('imageCouverture')->getData();
            if ($imageFile) {
                $fileContent = file_get_contents($imageFile->getPathname());
                $cours->setImageCouverture($fileContent);
            }

            // Keep owner fields synchronized for recruiter-scoped listings.
            $cours->setUser($user);
            $cours->setCreatedBy($user->getId());
            $this->em->persist($cours);
            $this->em->flush();
            $this->addFlash('success', 'Cours créé avec succès !');

            return $this->redirectToRoute('app_admin_cours_index');
        }

        return $this->render('BackOffice/dashboard/cours/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_cours_show', methods: ['GET'])]
    public function show(Request $request, Cours $cours): Response
    {
        $user = $this->requireUser();
        if (!$this->dashboardData->isAdmin($user)) {
            if ($cours->getUser()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException();
            }
            $request->getSession()->set('selected_cours_id', $cours->getId());
            $request->getSession()->remove('selected_module_id');
        }

        [$modules, $lessonsByModule] = $this->loadCourseTree($cours);

        return $this->render('BackOffice/dashboard/cours/show.html.twig', [
            'cours' => $cours,
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
            'course_image_base64' => $this->encodeBinaryToBase64($cours->getImageCouverture()),
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'app_admin_cours_export_pdf', methods: ['GET'])]
    public function exportPdf(Cours $cours): Response
    {
        $user = $this->requireUser();
        if (!$this->dashboardData->isAdmin($user)) {
            if ($cours->getUser()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException();
            }
        }

        [$modules, $lessonsByModule] = $this->loadCourseTree($cours);

        $html = $this->renderView('BackOffice/dashboard/cours/export_pdf.html.twig', [
            'cours' => $cours,
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
            'generated_at' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $cours->getTitre()) ?: 'cours';
        $fileName = sprintf('cours-%s-%s.pdf', trim($safeTitle, '-'), (new \DateTimeImmutable())->format('Ymd-His'));

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
            ]
        );
    }

    #[Route('/{id}/modifier', name: 'app_admin_cours_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cours $cours): Response
    {
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($cours->getCompetencesVisees() === null) {
                $cours->setCompetencesVisees('');
            }

            // Handle file upload for image
            $imageFile = $form->get('imageCouverture')->getData();
            if ($imageFile) {
                $fileContent = file_get_contents($imageFile->getPathname());
                $cours->setImageCouverture($fileContent);
            }

            $this->em->flush();
            $this->addFlash('success', 'Cours modifié avec succès !');

            return $this->redirectToRoute('app_admin_cours_index');
        }

        return $this->render('BackOffice/dashboard/cours/edit.html.twig', [
            'form'  => $form,
            'cours' => $cours,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_cours_delete', methods: ['POST'])]
    public function delete(Request $request, Cours $cours): Response
    {
        if ($this->isCsrfTokenValid('delete_cours_' . $cours->getId(), $request->getPayload()->get('_token'))) {
            $this->em->remove($cours);
            $this->em->flush();
            $this->addFlash('success', 'Cours supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_cours_index');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function loadCourseTree(Cours $cours): array
    {
        $modules = $this->moduleRepository->findByCours($cours);
        $lessonsByModule = [];

        if ($modules !== []) {
            $moduleIds = array_map(static fn ($module): int => (int) $module->getId(), $modules);
            $lessons = $this->leconRepository->findByModuleIds($moduleIds);
            foreach ($lessons as $lesson) {
                $moduleId = $lesson->getModuleId();
                if ($moduleId === null) {
                    continue;
                }
                $lessonsByModule[$moduleId][] = $lesson;
            }
        }

        return [$modules, $lessonsByModule];
    }

    private function encodeBinaryToBase64(mixed $blob): ?string
    {
        if ($blob === null) {
            return null;
        }

        if (is_resource($blob)) {
            $meta = stream_get_meta_data($blob);
            if (($meta['seekable'] ?? false) === true) {
                rewind($blob);
            }
            $content = stream_get_contents($blob);

            return is_string($content) && $content !== '' ? base64_encode($content) : null;
        }

        if (is_string($blob) && $blob !== '') {
            return base64_encode($blob);
        }

        return null;
    }
}
