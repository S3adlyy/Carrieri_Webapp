<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Cours;
use App\Entity\Module;
use App\Entity\User;
use App\Form\ModuleType;
use App\Repository\CoursRepository;
use App\Repository\ModuleRepository;
use App\Service\BackOfficeDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/modules')]
class ModuleController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private ModuleRepository $moduleRepository,
        private CoursRepository $coursRepository,
        private BackOfficeDashboardService $dashboardData,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'app_admin_modules_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);

        $selectedCours = null;
        if ($isAdmin) {
            $modules = $this->moduleRepository->findBy([], ['id' => 'DESC']);
        } else {
            $session = $request->getSession();
            $selectedCoursId = (int) $request->query->get('cours', (int) $session->get('selected_cours_id', 0));
            if ($selectedCoursId <= 0) {
                $this->addFlash('warning', 'Veuillez selectionner un cours.');

                return $this->redirectToRoute('app_admin_cours_index');
            }

            $selectedCours = $this->coursRepository->findOneBy(['id' => $selectedCoursId, 'user' => $user]);
            if ($selectedCours === null) {
                $session->remove('selected_cours_id');
                $session->remove('selected_module_id');
                $this->addFlash('danger', 'Cours invalide pour votre compte.');

                return $this->redirectToRoute('app_admin_cours_index');
            }

            if ((int) $session->get('selected_cours_id', 0) !== $selectedCours->getId()) {
                $session->remove('selected_module_id');
            }
            $session->set('selected_cours_id', $selectedCours->getId());
            $modules = $this->moduleRepository->findBy(['cours' => $selectedCours], ['ordre' => 'ASC', 'id' => 'ASC']);
        }

        return $this->render('BackOffice/dashboard/modules/index.html.twig', [
            'modules' => $modules,
            'is_admin_view' => $isAdmin,
            'selected_cours' => $selectedCours,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_modules_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $allowedCourses = $isAdmin
            ? $this->coursRepository->findBy([], ['id' => 'DESC'])
            : $this->coursRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $module = new Module();
        $prefillCoursId = (int) $request->query->get('cours', (int) $request->getSession()->get('selected_cours_id', 0));
        if (!$isAdmin && $prefillCoursId <= 0) {
            $this->addFlash('warning', 'Sélectionnez d\'abord un cours pour créer un module.');

            return $this->redirectToRoute('app_admin_cours_index');
        }

        $hasPrefilledCourse = false;
        if ($prefillCoursId > 0) {
            foreach ($allowedCourses as $candidateCours) {
                if ($candidateCours->getId() === $prefillCoursId) {
                    $module->setCours($candidateCours);
                    $module->setCoursId($candidateCours->getId());
                    $hasPrefilledCourse = true;
                    break;
                }
            }

            if (!$hasPrefilledCourse) {
                throw $this->createAccessDeniedException();
            }

            if (!$isAdmin) {
                $request->getSession()->set('selected_cours_id', $prefillCoursId);
            }
        }

        $form = $this->createForm(ModuleType::class, $module, [
            'cours_choices' => $allowedCourses,
            'lock_cours' => !$isAdmin && $hasPrefilledCourse,
            'include_ordre' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sync the scalar coursId from the relation
            if ($module->getCours()) {
                $module->setCoursId($module->getCours()->getId());
            }
            if ($module->getCoursId() !== null) {
                $module->setOrdre($this->moduleRepository->getNextOrdreForCours($module->getCoursId()));
            }
            $this->em->persist($module);
            $this->em->flush();
            $this->addFlash('success', 'Module créé avec succès !');

            $coursId = $module->getCoursId();
            if ($coursId !== null) {
                return $this->redirectToRoute('app_admin_modules_index', ['cours' => $coursId]);
            }

            return $this->redirectToRoute('app_admin_modules_index');
        }

        return $this->render('BackOffice/dashboard/modules/new.html.twig', [
            'form' => $form,
            'selected_cours' => $module->getCours(),
            'is_admin_view' => $isAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_modules_show', methods: ['GET'])]
    public function show(Request $request, Module $module): Response
    {
        $this->denyIfRecruiterCannotManageModule($module);
        $this->storeSelectionContext($request, $module);

        return $this->render('BackOffice/dashboard/modules/show.html.twig', [
            'module' => $module,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_modules_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Module $module): Response
    {
        $this->denyIfRecruiterCannotManageModule($module);
        $this->storeSelectionContext($request, $module);

        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $allowedCourses = $isAdmin
            ? $this->coursRepository->findBy([], ['id' => 'DESC'])
            : $this->coursRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $form = $this->createForm(ModuleType::class, $module, [
            'cours_choices' => $allowedCourses,
            'lock_cours' => !$isAdmin,
            'include_ordre' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($module->getCours()) {
                $module->setCoursId($module->getCours()->getId());
            }
            $this->em->flush();
            $this->addFlash('success', 'Module modifié avec succès !');

            $coursId = $module->getCoursId();
            if ($coursId !== null) {
                return $this->redirectToRoute('app_admin_modules_index', ['cours' => $coursId]);
            }

            return $this->redirectToRoute('app_admin_modules_index');
        }

        return $this->render('BackOffice/dashboard/modules/edit.html.twig', [
            'form'   => $form,
            'module' => $module,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_modules_delete', methods: ['POST'])]
    public function delete(Request $request, Module $module): Response
    {
        $this->denyIfRecruiterCannotManageModule($module);
        $this->storeSelectionContext($request, $module);

        $redirectCoursId = $module->getCoursId();
        $token = $request->getPayload()->get('_token');
        if ($this->isCsrfTokenValid('delete_module_' . $module->getId(), is_string($token) ? $token : null)) {
            $this->em->remove($module);
            $this->em->flush();
            $this->addFlash('success', 'Module supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        if ($redirectCoursId !== null) {
            return $this->redirectToRoute('app_admin_modules_index', ['cours' => $redirectCoursId]);
        }

        return $this->redirectToRoute('app_admin_modules_index');
    }

    #[Route('/reorder', name: 'app_admin_modules_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $isAdmin = $this->dashboardData->isAdmin($user);
        $coursId = $request->request->getInt('cours_id');
        $ids = array_values(array_filter(array_map('intval', (array) $request->request->all('ids'))));

        if ($coursId <= 0 || $ids === []) {
            return $this->json(['success' => false, 'message' => 'Données de réordonnancement invalides.'], 400);
        }

        $cours = $this->coursRepository->find($coursId);
        if (!$cours instanceof Cours) {
            return $this->json(['success' => false, 'message' => 'Cours introuvable.'], 404);
        }

        if (!$isAdmin && $cours->getUser()?->getId() !== $user->getId()) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $orderedModules = [];
        foreach ($ids as $moduleId) {
            $module = $this->moduleRepository->findOneBy(['id' => $moduleId, 'cours' => $cours]);
            if (!$module instanceof Module) {
                return $this->json(['success' => false, 'message' => 'Un module est invalide.'], 400);
            }
            $orderedModules[] = $module;
        }

        foreach ($orderedModules as $index => $module) {
            $module->setOrdre($index + 1);
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function requireUser(): User
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function denyIfRecruiterCannotManageModule(Module $module): void
    {
        $user = $this->requireUser();
        if ($this->dashboardData->isAdmin($user)) {
            return;
        }

        $owner = $module->getCours()?->getUser();
        if ($owner?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function storeSelectionContext(Request $request, Module $module): void
    {
        $user = $this->requireUser();
        if ($this->dashboardData->isAdmin($user)) {
            return;
        }

        if ($module->getCoursId() !== null) {
            $request->getSession()->set('selected_cours_id', $module->getCoursId());
        }
        $request->getSession()->set('selected_module_id', $module->getId());
    }
}

