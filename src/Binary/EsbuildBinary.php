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
    public const DEFAULT_VERSION = '0.25.2';

    /**
     * npm registry URL for platform-specific esbuild packages.
     * Format: https://registry.npmjs.org/@esbuild/{platform}/-/{platform}-{version}.tgz
     */
    private const NPM_REGISTRY_URL = 'https://registry.npmjs.org/@esbuild/%s/-/%s-%s.tgz';

    public function __construct(
        private readonly string $binaryDir,
        private readonly string $version = self::DEFAULT_VERSION,
    ) {}

    /**
     * Returns the absolute path to the esbuild binary (may not exist yet).
     */
    public function getPath(): string
    {
        return rtrim($this->binaryDir, '/\\')
            . \DIRECTORY_SEPARATOR . $this->version
            . \DIRECTORY_SEPARATOR . Platform::binaryFilename();
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
     * @throws BinaryDownloadException
     */
    public function download(?OutputInterface $output = null): void
    {
        $platform = Platform::detect();
        $url = sprintf(self::NPM_REGISTRY_URL, $platform, $platform, $this->version);
        $binaryPath = $this->getPath();
        $binaryDir = \dirname($binaryPath);

        if (!is_dir($binaryDir) && !mkdir($binaryDir, 0777, true) && !is_dir($binaryDir)) {
            throw new BinaryDownloadException(sprintf('Could not create directory "%s".', $binaryDir));
        }

        $output?->writeln(sprintf(
            '  <info>Downloading esbuild v%s for %s...</info>',
            $this->version,
            $platform
        ));

        $tarPath = $binaryPath . '.tmp.tgz';

        try {
            $this->downloadFile($url, $tarPath);
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
    }

    public function getVersion(): string
    {
        return $this->version;
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
            if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
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
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
