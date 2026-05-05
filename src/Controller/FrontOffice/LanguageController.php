<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
final class LanguageController extends AbstractController
{
    #[Route('/set-lang/{lang}', name: 'app_set_lang', methods: ['GET'])]
    public function setLang(Request $request, string $lang): RedirectResponse
    {
        if (TranslationService::isSupported($lang)) {
            $request->getSession()->set('app_lang', $lang);
        }

        // Redirect back to the page the user was on
        $referer = $request->headers->get('referer') ?? $this->generateUrl('app_candidate_cours');
        return $this->redirect($referer);
    }
}
