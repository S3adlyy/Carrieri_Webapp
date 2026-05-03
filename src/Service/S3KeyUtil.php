<?php
declare(strict_types=1);

namespace App\Service;

final class S3KeyUtil
{
    public static function profilePicKey(int $candidateId, string $extNoDot): string
    {
        return sprintf('workspace/candidate-%d/profile-pic.%s', $candidateId, strtolower($extNoDot));
    }

    public static function orgLogoKey(int $candidateId, string $extNoDot): string
    {
        return sprintf('workspace/candidate-%d/org-logo.%s', $candidateId, strtolower($extNoDot));
    }

    public static function artifactFileKey(
        int $candidateId,
        int $trackId,
        int $artifactId,
        string $extNoDot
    ): string {
        return sprintf(
            'workspace/candidate-%d/track-%d/artifact-%d/%s.%s',
            $candidateId,
            $trackId,
            $artifactId,
            bin2hex(random_bytes(8)),
            strtolower($extNoDot)
        );
    }

    public static function extNoDotOrDefault(string $filename, string $default = 'bin'): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return $ext !== '' ? strtolower($ext) : $default;
    }

    public static function contentTypeFromExt(string $extNoDot): string
    {
        return match (strtolower($extNoDot)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'txt' => 'text/plain',
            'html', 'twig' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'php' => 'text/x-php',
            'java' => 'text/x-java-source',
            'py' => 'text/x-python',
            'sql' => 'application/sql',
            default => 'application/octet-stream',
        };
    }
}