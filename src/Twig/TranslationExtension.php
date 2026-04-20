<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for language translation.
 *
 * Usage in templates:
 *   {{ "Bonjour"|trans_lang }}          ← auto-reads lang from session
 *   {{ translation_languages() }}        ← returns all supported languages
 *   {{ current_lang() }}                 ← returns current session lang (e.g. 'en')
 */
final class TranslationExtension extends AbstractExtension
{
    public function __construct(
        private TranslationService $translationService,
        private RequestStack $requestStack,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'trans_lang',
                function (string $text): string {
                    $lang = $this->getCurrentLang();
                    return $this->translationService->translate($text, $lang);
                },
                ['is_safe' => ['html']]
            ),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'translation_languages',
                fn (): array => TranslationService::LANGUAGES
            ),
            new TwigFunction(
                'current_lang',
                fn (): string => $this->getCurrentLang()
            ),
        ];
    }

    private function getCurrentLang(): string
    {
        $session = $this->requestStack->getSession();
        $lang    = (string) ($session->get('app_lang', 'fr'));
        return TranslationService::isSupported($lang) ? $lang : 'fr';
    }
}
