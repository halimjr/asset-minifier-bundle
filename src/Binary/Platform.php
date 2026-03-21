<?php

namespace HalimJr\AssetMinifierBundle\Binary;

use HalimJr\AssetMinifierBundle\Exception\PlatformNotSupportedException;

/**
 * Detects the current OS and CPU architecture to select the correct esbuild npm package.
 */
final class Platform
{
    /**
     * Returns the npm platform package name (e.g. "linux-x64", "win32-arm64").
     *
     * @throws PlatformNotSupportedException
     */
    public static function detect(): string
    {
        return self::os() . '-' . self::arch();
    }

    /**
     * Returns the path of the esbuild binary inside the npm tarball.
     * Windows: package/esbuild.exe
     * Unix:    package/bin/esbuild
     */
    public static function binaryPathInTar(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'package/esbuild.exe' : 'package/bin/esbuild';
    }

    /**
     * Returns the filename of the esbuild binary on disk.
     */
    public static function binaryFilename(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'esbuild.exe' : 'esbuild';
    }

    private static function os(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux'   => 'linux',
            'Darwin'  => 'darwin',
            'Windows' => 'win32',
            default   => throw new PlatformNotSupportedException(
                sprintf(
                    'Operating system "%s" is not supported. Supported: Linux, macOS, Windows. '
                    . 'Please open an issue at https://github.com/halimjr/asset-minifier-bundle.',
                    PHP_OS_FAMILY
                )
            ),
        };
    }

    private static function arch(): string
    {
        $machine = strtolower(php_uname('m'));

        return match (true) {
            in_array($machine, ['x86_64', 'amd64', 'x64'], true) => 'x64',
            in_array($machine, ['aarch64', 'arm64'], true)        => 'arm64',
            default => throw new PlatformNotSupportedException(
                sprintf(
                    'CPU architecture "%s" is not supported. Supported: x86_64, arm64. '
                    . 'Please open an issue at https://github.com/halimjr/asset-minifier-bundle.',
                    $machine
                )
            ),
        };
    }
}
