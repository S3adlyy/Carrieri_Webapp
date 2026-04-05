<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Module;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class ModuleController extends AbstractController
{
    #[Route('/modules/{id}', name: 'app_candidate_module_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Module $module): RedirectResponse
    {
        $cours = $module->getCours();
        if ($cours === null || $cours->getId() === null) {
            throw $this->createNotFoundException();
        }

        // Keep the current UX: module entry points back to the same course detail page.
        return $this->redirectToRoute('app_candidate_cours_show', ['id' => $cours->getId()]);
    }
}

