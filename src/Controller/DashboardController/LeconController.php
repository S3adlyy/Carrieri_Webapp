<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Lecon;
use App\Entity\Module;
use App\Entity\User;
use App\Form\LeconType;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Service\BackOfficeDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lecons')]
class LeconController extends AbstractController
{
    public function __construct(
        private LeconRepository $leconRepository,
        private ModuleRepository $moduleRepository,
        private BackOfficeDashboardService $dashboardData,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'app_admin_lecons_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);

        $selectedModule = null;
        if ($isAdmin) {
            $lecons = $this->leconRepository->findBy([], ['id' => 'DESC']);
        } else {
            $session = $request->getSession();
            $selectedModuleId = (int) $request->query->get('module', (int) $session->get('selected_module_id', 0));
            if ($selectedModuleId <= 0) {
                $this->addFlash('warning', 'Veuillez selectionner un module.');

                return $this->redirectToRoute('app_admin_modules_index');
            }

            $selectedModule = $this->findRecruiterModule($user, $selectedModuleId);
            if ($selectedModule === null) {
                $session->remove('selected_module_id');
                $this->addFlash('danger', 'Module invalide pour votre compte.');

                return $this->redirectToRoute('app_admin_modules_index');
            }

            $session->set('selected_module_id', $selectedModule->getId());
            if ($selectedModule->getCoursId() !== null) {
                $session->set('selected_cours_id', $selectedModule->getCoursId());
            }

            $lecons = $this->leconRepository->findBy(['module' => $selectedModule], ['ordre' => 'ASC', 'id' => 'ASC']);
        }

        return $this->render('BackOffice/dashboard/lecons/index.html.twig', [
            'lecons' => $lecons,
            'is_admin_view' => $isAdmin,
            'selected_module' => $selectedModule,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_lecons_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $allowedModules = $isAdmin
            ? $this->moduleRepository->findBy([], ['id' => 'DESC'])
            : $this->moduleRepository->findForRecruiter($user);

        $lecon = new Lecon();
        $prefillModuleId = (int) $request->query->get('module', (int) $request->getSession()->get('selected_module_id', 0));
        if (!$isAdmin && $prefillModuleId <= 0) {
            $this->addFlash('warning', 'Sélectionnez d\'abord un module pour créer une leçon.');

            return $this->redirectToRoute('app_admin_modules_index');
        }

        $hasPrefilledModule = false;
        if ($prefillModuleId > 0) {
            foreach ($allowedModules as $candidateModule) {
                if ($candidateModule->getId() === $prefillModuleId) {
                    $lecon->setModule($candidateModule);
                    $lecon->setModuleId($candidateModule->getId());
                    $hasPrefilledModule = true;
                    break;
                }
            }

            if (!$hasPrefilledModule) {
                throw $this->createAccessDeniedException();
            }

            if (!$isAdmin) {
                $request->getSession()->set('selected_module_id', $prefillModuleId);
                $coursId = $lecon->getModule()?->getCoursId();
                if ($coursId !== null) {
                    $request->getSession()->set('selected_cours_id', $coursId);
                }
            }
        }

        $form = $this->createForm(LeconType::class, $lecon, [
            'module_choices' => $allowedModules,
            'lock_module' => !$isAdmin && $hasPrefilledModule,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sync scalar moduleId from relation
            if ($lecon->getModule()) {
                $lecon->setModuleId($lecon->getModule()->getId());
            }
            $this->em->persist($lecon);
            $this->em->flush();
            $this->addFlash('success', 'Leçon créée avec succès !');

            $coursId = $lecon->getModule()?->getCoursId();
            if ($coursId !== null) {
                return $this->redirectToRoute('app_admin_cours_show', ['id' => $coursId]);
            }

            return $this->redirectToRoute('app_admin_lecons_index');
        }

        return $this->render('BackOffice/dashboard/lecons/new.html.twig', [
            'form' => $form,
            'selected_module' => $lecon->getModule(),
            'is_admin_view' => $isAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_lecons_show', methods: ['GET'])]
    public function show(Request $request, Lecon $lecon): Response
    {
        $this->denyIfRecruiterCannotManageLesson($lecon);
        $this->storeSelectionContext($request, $lecon);

        return $this->render('BackOffice/dashboard/lecons/show.html.twig', [
            'lecon' => $lecon,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_lecons_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lecon $lecon): Response
    {
        $this->denyIfRecruiterCannotManageLesson($lecon);
        $this->storeSelectionContext($request, $lecon);

        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $allowedModules = $isAdmin
            ? $this->moduleRepository->findBy([], ['id' => 'DESC'])
            : $this->moduleRepository->findForRecruiter($user);

        $form = $this->createForm(LeconType::class, $lecon, [
            'module_choices' => $allowedModules,
            'lock_module' => !$isAdmin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($lecon->getModule()) {
                $lecon->setModuleId($lecon->getModule()->getId());
            }
            $this->em->flush();
            $this->addFlash('success', 'Leçon modifiée avec succès !');

            $coursId = $lecon->getModule()?->getCoursId();
            if ($coursId !== null) {
                return $this->redirectToRoute('app_admin_cours_show', ['id' => $coursId]);
            }

            return $this->redirectToRoute('app_admin_lecons_index');
        }

        return $this->render('BackOffice/dashboard/lecons/edit.html.twig', [
            'form'  => $form,
            'lecon' => $lecon,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_lecons_delete', methods: ['POST'])]
    public function delete(Request $request, Lecon $lecon): Response
    {
        $this->denyIfRecruiterCannotManageLesson($lecon);
        $this->storeSelectionContext($request, $lecon);

        $redirectCoursId = $lecon->getModule()?->getCoursId();
        if ($this->isCsrfTokenValid('delete_lecon_' . $lecon->getId(), $request->getPayload()->get('_token'))) {
            $this->em->remove($lecon);
            $this->em->flush();
            $this->addFlash('success', 'Leçon supprimée avec succès.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        if ($redirectCoursId !== null) {
            return $this->redirectToRoute('app_admin_cours_show', ['id' => $redirectCoursId]);
        }

        return $this->redirectToRoute('app_admin_lecons_index');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function denyIfRecruiterCannotManageLesson(Lecon $lecon): void
    {
        $user = $this->requireUser();
        if ($this->dashboardData->isAdmin($user)) {
            return;
        }

        $owner = $lecon->getModule()?->getCours()?->getUser();
        if ($owner?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function findRecruiterModule(User $user, int $moduleId): ?Module
    {
        $module = $this->moduleRepository->find($moduleId);
        if (!$module instanceof Module) {
            return null;
        }

        $ownerId = $module->getCours()?->getUser()?->getId();
        if ($ownerId !== $user->getId()) {
            return null;
        }

        return $module;
    }

    private function storeSelectionContext(Request $request, Lecon $lecon): void
    {
        $user = $this->requireUser();
        if ($this->dashboardData->isAdmin($user)) {
            return;
        }

        $moduleId = $lecon->getModuleId();
        if ($moduleId !== null) {
            $request->getSession()->set('selected_module_id', $moduleId);
        }

        $coursId = $lecon->getModule()?->getCoursId();
        if ($coursId !== null) {
            $request->getSession()->set('selected_cours_id', $coursId);
        }
    }
}
