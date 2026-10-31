<?php

namespace HalimJr\AssetMinifierBundle\Binary;

use HalimJr\AssetMinifierBundle\Exception\BinaryDownloadException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manages the esbuild binary: resolves its path, checks availability, and downloads it on demand.
 *
 * Binaries are downloaded from the npm registry and cached in the configured binary directory.
 */
class EsbuildBinary
{
    public const DEFAULT_VERSION = '0.28.1';

    /**
     * SHA-512 integrity hashes for the npm tarballs of known esbuild versions, keyed by version and platform.
     *
     * These are checked instead of hashes fetched at runtime, so a compromised registry or tampered
     * response cannot supply a matching hash. Versions not listed here fall back to the registry hash.
     */
    public const KNOWN_INTEGRITY = [
        '0.28.1' => [
            'linux-x64'    => 'sha512-u/anNYF2mmVOEDwLtnQ1wOr3EZ9sTNGLWrsYGYwHWzGA3Si84IOkHXlbWTD1NB+9/1lcnweYKO54uhxZydNzfA==',
            'linux-arm64'  => 'sha512-yHs+0uc8+nvEAfAfxrWQKK5peSNzBc4PegcMO0EJ2hT71uA7vB8Ihg2e77R2P7SG5uYjPbHlLLmve4LLLRCf0g==',
            'darwin-x64'   => 'sha512-zfdzgK9ACBNZLI/CyHTOx81SyNbM6YXn7rxSgX97VjyiPl9W1i4Ka4fgKECEoFCKGpvBj5qArWIGgQjOwkgskQ==',
            'darwin-arm64' => 'sha512-TZbWkQY7kvTAXbXUT7uVACR5cMHsDiSz9z7ZKAX/RTq/WJEk3QyRr0wZpNhBDX+/0CtdqUIJlOiodQcta6tY3Q==',
            'win32-x64'    => 'sha512-bm4Mowrv+GXMlpWX++EcXw/iLyd1o3+bJkC2DkWXYVvgZCqD/bSj9ctZeAMC3cIxgjRVR2Dufaiu4YPxr5gW1A==',
            'win32-arm64'  => 'sha512-lCv9eK/H6ZJWbE7bh2nw54CZ9M2nupBxJcTsdk/QQnWkdSjKGuxmmH8/GWrlT1eMmZfn4dGcCjRte397WqfQXA==',
        ],
    ];

    /**
     * npm registry URL for platform-specific esbuild packages.
     * Format: https://registry.npmjs.org/@esbuild/{platform}/-/{platform}-{version}.tgz
     */
    private const NPM_REGISTRY_URL = 'https://registry.npmjs.org/@esbuild/%s/-/%s-%s.tgz';

    public function __construct(
        private readonly string $binaryDir,
        private readonly string $version = self::DEFAULT_VERSION,
        private readonly ?string $customBinaryPath = null,
    ) {}

    /**
     * Returns the absolute path to the esbuild binary (may not exist yet).
     *
     * When binary_path is configured, that path is returned directly.
     * Otherwise the bundle-managed versioned path is used.
     */
    public function getPath(): string
    {
        return $this->customBinaryPath ?? (
            rtrim($this->binaryDir, '/\\')
            . \DIRECTORY_SEPARATOR . $this->version
            . \DIRECTORY_SEPARATOR . Platform::binaryFilename()
        );
    }

    /**
     * Returns true if the binary is managed automatically by this bundle (i.e. binary_path is not set).
     */
    public function isManaged(): bool
    {
        return $this->customBinaryPath === null;
    }

    /**
     * Returns true if the binary exists and is executable.
     */
    public function isAvailable(): bool
    {
        $path = $this->getPath();

        if (!is_file($path)) {
            return false;
        }

        return PHP_OS_FAMILY === 'Windows' || is_executable($path);
    }

    /**
     * Downloads and extracts the esbuild binary for the current platform.
     *
     * Uses an exclusive file lock to prevent concurrent downloads in multi-worker deployments.
     * The tarball integrity is verified before extraction against the SHA-512 hash pinned in
     * KNOWN_INTEGRITY, or, for versions not pinned there, the hash published by the npm registry.
     *
     * @throws BinaryDownloadException
     */
    public function download(?OutputInterface $output = null): void
    {
        if ($this->customBinaryPath !== null) {
            throw new BinaryDownloadException(sprintf(
                'Cannot auto-download when "binary_path" is configured. '
                . 'Ensure the binary exists at "%s".',
                $this->customBinaryPath
            ));
        }

        $platform   = Platform::detect();
        $url        = sprintf(self::NPM_REGISTRY_URL, $platform, $platform, $this->version);
        $binaryPath = $this->getPath();
        $binaryDir  = \dirname($binaryPath);
        $baseDir    = rtrim($this->binaryDir, '/\\');

        // Create the base directory first so we have a stable place for the lock file.
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            throw new BinaryDownloadException(sprintf(
                'Could not create directory "%s". '
                . 'If your filesystem is read-only (e.g. in a Docker container), '
                . 'pre-download the binary during the image build step or set "binary_path" '
                . 'in your asset_minifier configuration.',
                $baseDir
            ));
        }

        $lockPath = $baseDir . \DIRECTORY_SEPARATOR . '.download.lock';
        $lock = @fopen($lockPath, 'c');

        if ($lock === false) {
            throw new BinaryDownloadException(sprintf('Could not create lock file "%s".', $lockPath));
        }

        // Block until any concurrent download finishes, then re-check availability.
        flock($lock, \LOCK_EX);

        try {
            if ($this->isAvailable()) {
                return;
            }

            if (!is_dir($binaryDir) && !mkdir($binaryDir, 0755, true) && !is_dir($binaryDir)) {
                throw new BinaryDownloadException(sprintf(
                    'Could not create directory "%s". '
                    . 'If your filesystem is read-only (e.g. in a Docker container), '
                    . 'pre-download the binary during the image build step or set "binary_path" '
                    . 'in your asset_minifier configuration.',
                    $binaryDir
                ));
            }

            $output?->writeln(sprintf(
                '  <info>Downloading esbuild v%s for %s...</info>',
                $this->version,
                $platform
            ));

            $tarPath = $binaryPath . '.tmp.tgz';

            try {
                $integrity = self::KNOWN_INTEGRITY[$this->version][$platform]
                    ?? $this->fetchExpectedIntegrity($platform);
                $this->downloadFile($url, $tarPath);

                $output?->writeln('  <info>Verifying integrity...</info>');
                $this->verifyIntegrity($tarPath, $integrity);

                $this->extractBinary($tarPath, $binaryPath);
            } finally {
                if (is_file($tarPath)) {
                    @unlink($tarPath);
                }
            }

            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($binaryPath, 0755);
            }

            $output?->writeln('  <info>esbuild downloaded successfully.</info>');

            $this->cleanupOldVersions();
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDownloadUrl(): string
    {
        $platform = Platform::detect();
        return sprintf('https://registry.npmjs.org/@esbuild/%s/-/%s-%s.tgz', $platform, $platform, $this->version);
    }

    /**
     * Fetches the expected SHA-512 integrity hash from the npm registry metadata API.
     *
     * @throws BinaryDownloadException
     */
    private function fetchExpectedIntegrity(string $platform): string
    {
        $metaUrl = sprintf('https://registry.npmjs.org/@esbuild/%s/%s', $platform, $this->version);

        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'max_redirects'   => 3,
                'timeout'         => 15,
                'header'          => "User-Agent: halimjr/asset-minifier-bundle\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $json = @file_get_contents($metaUrl, false, $context);

        if ($json === false) {
            throw new BinaryDownloadException(sprintf(
                'Failed to fetch package metadata from "%s". Check your internet connection.',
                $metaUrl
            ));
        }

        $data = json_decode($json, true);

        if (!isset($data['dist']['integrity'])) {
            throw new BinaryDownloadException(sprintf(
                'Could not find integrity hash in npm registry response for "%s".',
                $metaUrl
            ));
        }

        return $data['dist']['integrity'];
    }

    /**
     * Verifies a file's SHA-512 hash against a Subresource Integrity string (e.g. "sha512-...").
     *
     * @throws BinaryDownloadException
     */
    private function verifyIntegrity(string $filePath, string $sriHash): void
    {
        if (!str_starts_with($sriHash, 'sha512-')) {
            throw new BinaryDownloadException(sprintf(
                'Unexpected integrity hash format "%s". Expected "sha512-<base64>" from npm registry.',
                $sriHash
            ));
        }

        $expected = substr($sriHash, 7);
        $actual   = base64_encode(hash_file('sha512', $filePath, true));

        if (!hash_equals($expected, $actual)) {
            throw new BinaryDownloadException(
                'esbuild binary integrity check failed. The downloaded file may be corrupted or tampered with. '
                . 'Please try again or report the issue at https://github.com/halimjr/asset-minifier-bundle.'
            );
        }
    }

    /**
     * @throws BinaryDownloadException
     */
    private function downloadFile(string $url, string $destination): void
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'max_redirects'   => 5,
                'timeout'         => 60,
                'header'          => "User-Agent: halimjr/asset-minifier-bundle\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $source = @fopen($url, 'r', false, $context);
        if ($source === false) {
            throw new BinaryDownloadException(sprintf(
                'Failed to download esbuild from "%s". '
                . 'Check your internet connection or run "php bin/console asset-minifier:download" manually.',
                $url
            ));
        }

        $dest = @fopen($destination, 'wb');
        if ($dest === false) {
            fclose($source);
            throw new BinaryDownloadException(
                sprintf('Could not write to "%s". Check directory permissions.', $destination)
            );
        }

        stream_copy_to_stream($source, $dest);
        fclose($source);
        fclose($dest);
    }

    /**
     * @throws BinaryDownloadException
     */
    private function extractBinary(string $tarPath, string $binaryPath): void
    {
        $binaryInTar = Platform::binaryPathInTar();
        $tmpDir = $tarPath . '_dir';

        try {
            if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                throw new BinaryDownloadException(sprintf('Could not create temp directory "%s".', $tmpDir));
            }

            try {
                $archive = new \PharData($tarPath);
                $archive->extractTo($tmpDir, $binaryInTar, true);
            } catch (\Exception $e) {
                throw new BinaryDownloadException(sprintf(
                    'Failed to extract esbuild tarball: %s',
                    $e->getMessage()
                ), 0, $e);
            }

            $extractedPath = $tmpDir . \DIRECTORY_SEPARATOR
                . str_replace('/', \DIRECTORY_SEPARATOR, $binaryInTar);

            if (!is_file($extractedPath)) {
                throw new BinaryDownloadException(sprintf(
                    'Expected binary "%s" was not found in the archive. '
                    . 'Please open an issue at https://github.com/halimjr/asset-minifier-bundle.',
                    $binaryInTar
                ));
            }

            if (!rename($extractedPath, $binaryPath)) {
                throw new BinaryDownloadException(
                    sprintf('Could not move esbuild binary to "%s".', $binaryPath)
                );
            }
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    /**
     * Removes version directories that do not match the current version.
     * Prevents var/esbuild/ from accumulating stale binaries after upgrades.
     */
    private function cleanupOldVersions(): void
    {
        $baseDir = rtrim($this->binaryDir, '/\\');

        if (!is_dir($baseDir)) {
            return;
        }

        foreach (new \DirectoryIterator($baseDir) as $item) {
            // Never follow symlinks: getRealPath() would resolve them to a directory outside $baseDir.
            if ($item->isDot() || $item->isLink() || !$item->isDir() || $item->getFilename() === $this->version) {
                continue;
            }

            $this->removeDirectory($item->getRealPath());
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            // Remove links themselves rather than resolving them to their targets.
            if ($item->isLink()) {
                @unlink($item->getPathname()) || @rmdir($item->getPathname());
                continue;
            }

            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
