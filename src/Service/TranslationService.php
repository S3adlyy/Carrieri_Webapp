<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Lightweight translation service using the free Google Translate API.
 * No API key required for small volumes.
 */
final class TranslationService
{
    /** Supported languages: code => label */
    public const LANGUAGES = [
        'fr' => '🇫🇷 Français',
        'en' => '🇬🇧 English',
        'ar' => '🇸🇦 العربية',
        'es' => '🇪🇸 Español',
        'de' => '🇩🇪 Deutsch',
        'it' => '🇮🇹 Italiano',
    ];

    private const SOURCE_LANG = 'fr';
    private const API_URL    = 'https://translate.googleapis.com/translate_a/single';
    private const TIMEOUT    = 5;   // seconds

    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * Translate a string to the target language.
     * Returns the original string if translation fails or if target = source.
     */
    public function translate(string $text, string $targetLang, string $sourceLang = self::SOURCE_LANG): string
    {
        $text = trim($text);
        if ($text === '' || $targetLang === $sourceLang || !self::isSupported($targetLang)) {
            return $text;
        }

        // Cache key: stable hash of text + source + target language
        $cacheKey = 'trans_' . $sourceLang . '_to_' . $targetLang . '_' . md5($text);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($text, $targetLang, $sourceLang): string {
            $item->expiresAfter(86400 * 7); // cache 7 days

            $translated = $this->callApi($text, $targetLang, $sourceLang);
            return $translated !== '' ? $translated : $text;
        });
    }

    /**
     * Is this language code valid?
     */
    public static function isSupported(string $lang): bool
    {
        return isset(self::LANGUAGES[$lang]);
    }

    /**
     * Calls the Google Translate unofficial API.
     */
    private function callApi(string $text, string $targetLang, string $sourceLang): string
    {
        $url = self::API_URL . '?' . http_build_query([
                'client' => 'gtx',
                'sl'     => $sourceLang,
                'tl'     => $targetLang,
                'dt'     => 't',
                'q'      => $text,
            ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => self::TIMEOUT,
                'header'  => "User-Agent: Mozilla/5.0\r\n",
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        try {
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false || $raw === '') {
                return '';
            }

            $data = json_decode($raw, true);

            // Response structure: [[["translated","original",...], ...], ...]
            if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                return '';
            }

            $result = '';
            foreach ($data[0] as $chunk) {
                if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
                    $result .= $chunk[0];
                }
            }

            return trim($result);
        } catch (\Throwable) {
            return '';
        }
    }
}
