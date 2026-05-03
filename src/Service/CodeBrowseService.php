<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\FileObject;
use ZipArchive;

/**
 * Downloads a ZIP artifact from S3 and provides a file tree + entry content.
 * Used exclusively by CodeViewerController.
 */
final class CodeBrowseService
{
    public function __construct(
        private readonly S3Service $s3,
    ) {}

    /**
     * Returns a nested file tree array from the ZIP.
     * Shape: [['name' => 'src', 'type' => 'dir', 'children' => [...]], ...]
     */
    public function buildTree(FileObject $fo): array
    {
        $tmpPath = $this->s3->downloadToTempFile($fo->getStorageKey());

        try {
            $zip  = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new \RuntimeException('Cannot open ZIP file.');
            }

            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false) {
                    $entries[] = $name;
                }
            }
            $zip->close();

            return $this->entriesToTree($entries);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Returns the text content of a single entry inside the ZIP.
     * Binary files return a placeholder message instead of garbled bytes.
     */
    public function readEntry(FileObject $fo, string $entryPath): string
    {
        $tmpPath = $this->s3->downloadToTempFile($fo->getStorageKey());

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new \RuntimeException('Cannot open ZIP file.');
            }

            $content = $zip->getFromName($entryPath);
            $zip->close();

            if ($content === false) {
                return '// File not found in archive: ' . $entryPath;
            }

            // Detect binary content — return placeholder
            if (!mb_check_encoding($content, 'UTF-8') || str_contains(substr($content, 0, 512), "\x00")) {
                return sprintf('// Binary file: %s (%d bytes) — download to view.', basename($entryPath), strlen($content));
            }

            return $content;
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Detects language from file extension for Monaco Editor.
     */
    public static function languageFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'php'               => 'php',
            'js', 'mjs', 'cjs' => 'javascript',
            'ts'                => 'typescript',
            'html', 'twig'      => 'html',
            'css', 'scss'       => 'css',
            'json'              => 'json',
            'xml'               => 'xml',
            'yaml', 'yml'       => 'yaml',
            'java'              => 'java',
            'py'                => 'python',
            'c', 'h'            => 'c',
            'cpp', 'hpp'        => 'cpp',
            'cs'                => 'csharp',
            'go'                => 'go',
            'rs'                => 'rust',
            'sql'               => 'sql',
            'sh', 'bash'        => 'shell',
            'md'                => 'markdown',
            'txt'               => 'plaintext',
            default             => 'plaintext',
        };
    }

    // ─── Private ─────────────────────────────────────────────────────────

    private function entriesToTree(array $entries): array
    {
        $root = [];

        foreach ($entries as $entry) {
            $parts   = explode('/', rtrim($entry, '/'));
            $isDir   = str_ends_with($entry, '/');
            $current = &$root;

            foreach ($parts as $i => $part) {
                if ($part === '') continue;

                $found = false;
                foreach ($current as &$node) {
                    if ($node['name'] === $part) {
                        $current = &$node['children'];
                        $found = true;
                        break;
                    }
                }
                unset($node);

                if (!$found) {
                    $isLast = ($i === count($parts) - 1);
                    $type   = ($isLast && !$isDir) ? 'file' : 'dir';
                    $path   = implode('/', array_slice($parts, 0, $i + 1)) . ($type === 'dir' ? '/' : '');
                    $current[] = [
                        'name'     => $part,
                        'type'     => $type,
                        'path'     => $path,
                        'children' => [],
                    ];
                    $last = &$current[count($current) - 1];
                    $current = &$last['children'];
                }
            }
        }

        return $this->sortTree($root);
    }

    private function sortTree(array $nodes): array
    {
        usort($nodes, function (array $a, array $b) {
            // dirs before files, then alphabetical
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $node['children'] = $this->sortTree($node['children']);
            }
        }
        return $nodes;
    }
}