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
     * @return array<array<string, mixed>>
     */
    public function buildTree(FileObject $fo): array
    {
        $tmpPath = $this->s3->downloadToTempFile((string) $fo->getStorageKey());

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
        $tmpPath = $this->s3->downloadToTempFile((string) $fo->getStorageKey());

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new \RuntimeException('Cannot open ZIP file.');
            }

            $requested = str_replace('\\', '/', trim($entryPath));
            $requested = ltrim($requested, '/');

            $candidates = array_values(array_unique([
                $requested,
                rawurldecode($requested),
                str_replace('//', '/', $requested),
            ]));

            $content = false;

            foreach ($candidates as $candidate) {
                $content = $zip->getFromName($candidate);
                if ($content !== false) {
                    $entryPath = $candidate;
                    break;
                }
            }

            if ($content === false) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name === false) {
                        continue;
                    }

                    $normalizedName = ltrim(str_replace('\\', '/', $name), '/');

                    if (strcasecmp($normalizedName, $requested) === 0) {
                        $content = $zip->getFromIndex($i);
                        $entryPath = $normalizedName;
                        break;
                    }
                }
            }

            $zip->close();

            if ($content === false) {
                return '// File not found in archive: ' . $requested;
            }

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

    /**
     * @param array<string> $entries
     * @return array<array<string, mixed>>
     */
    private function entriesToTree(array $entries): array
    {
        $root = [];

        foreach ($entries as $entry) {
            $entry = str_replace('\\', '/', $entry);
            $entry = ltrim($entry, '/');

            if ($entry === '') {
                continue;
            }

            $isDir = str_ends_with($entry, '/');
            $trimmed = rtrim($entry, '/');

            if ($trimmed === '') {
                continue;
            }

            $parts = explode('/', $trimmed);
            $current =& $root;
            $builtPath = '';

            foreach ($parts as $i => $part) {
                if ($part === '') {
                    continue;
                }

                $isLast = $i === count($parts) - 1;
                $type = ($isLast && !$isDir) ? 'file' : 'dir';
                $builtPath = $builtPath === '' ? $part : $builtPath . '/' . $part;
                $nodePath = $type === 'dir' ? $builtPath . '/' : $builtPath;

                $index = null;
                foreach ($current as $k => $node) {
                    if (($node['name'] ?? null) === $part && ($node['type'] ?? null) === $type) {
                        $index = $k;
                        break;
                    }
                }

                if ($index === null) {
                    $current[] = [
                        'name' => $part,
                        'type' => $type,
                        'path' => $nodePath,
                        'children' => [],
                    ];
                    $index = array_key_last($current);
                }

                if ($type === 'dir') {
                    $current =& $current[$index]['children'];
                }
            }
        }

        return $this->sortTree($root);
    }

    /**
     * @param array<array<string, mixed>> $nodes
     * @param list<string> $parts
     * @param array<string, mixed> $newNode
     */
    private function addTreeNode(array &$nodes, array $parts, array $newNode): void
    {
        $name = $parts[0];

        foreach ($nodes as &$node) {
            if ($node['name'] !== $name) {
                continue;
            }

            if (count($parts) > 1) {
                $children = is_array($node['children']) ? $node['children'] : [];
                $this->addTreeNode($children, array_slice($parts, 1), $newNode);
                $node['children'] = $children;
            }

            return;
        }

        $nodes[] = $newNode;
    }

    /**
     * @param array<array<string, mixed>> $nodes
     * @return array<array<string, mixed>>
     */
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
    public function readRawFile(FileObject $fo): string
    {
        $tmpPath = $this->s3->downloadToTempFile((string) $fo->getStorageKey());

        try {
            $content = file_get_contents($tmpPath);
            if ($content === false) {
                throw new \RuntimeException('Unable to read file.');
            }

            if (!mb_check_encoding($content, 'UTF-8') || str_contains(substr($content, 0, 512), "\0")) {
                return '[Binary file cannot be previewed as text]';
            }

            return $content;
        } finally {
            @unlink($tmpPath);
        }
    }
    public static function languageFromStorageKey(string $path): string
    {
        return self::languageFromPath($path);
    }
}
