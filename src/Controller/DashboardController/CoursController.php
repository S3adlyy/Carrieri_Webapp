<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Cours;
use App\Entity\User;
use App\Form\CoursType;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Service\BackOfficeDashboardService;
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
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'app_admin_cours_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/cours/index.html.twig', [
            'cours_list'    => $this->dashboardData->listCours($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
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

        return $this->render('BackOffice/dashboard/cours/show.html.twig', [
            'cours' => $cours,
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_cours_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cours $cours): Response
    {
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
}
