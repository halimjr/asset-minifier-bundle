<?php

namespace HalimJr\AssetMinifierBundle\Compiler;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Process\Process;

/**
 * Minifies JavaScript and CSS assets using esbuild.
 *
 * Registered with priority -100 so it always runs after Symfony's
 * JavaScriptImportPathCompiler, which must resolve import paths first.
 *
 * When esbuild fails (syntax error, unsupported syntax, etc.) the compiler
 * logs a warning with the full esbuild error output and returns the original
 * unminified content so the build always completes — no file blocks the others.
 */
class EsbuildCompiler implements AssetCompilerInterface
{
    private bool $initialized = false;

    public function __construct(
        private readonly EsbuildBinary $binary,
        private readonly bool $sourceMaps = false,
        private readonly bool $debug = false,
        private readonly bool $minifyJs = true,
        private readonly bool $minifyCss = true,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function supports(MappedAsset $asset): bool
    {
        if ($asset->isVendor) {
            return false;
        }

        return match ($asset->publicExtension) {
            'js'  => $this->minifyJs,
            'css' => $this->minifyCss,
            default => false,
        };
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        if (!$this->initialized) {
            if (!$this->binary->isAvailable()) {
                $this->binary->download();
            }
            $this->initialized = true;
        }

        $ext = $asset->publicExtension;
        $tmp = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'esbuild_' . bin2hex(random_bytes(8)) . '.' . $ext;

        try {
            file_put_contents($tmp, $content);

            $args = [
                $this->binary->getPath(),
                '--minify',
                '--log-level=error',  // capture errors; suppress info/warnings
            ];

            if ('js' === $ext) {
                $args[] = '--format=esm';
            } elseif ('css' === $ext) {
                $args[] = '--supported:hex-rgba=false';
            }

            if ($this->sourceMaps) {
                $args[] = '--sourcemap=inline';
            }

            $args[] = $tmp;

            $process = new Process($args);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());

                // Replace the temp path with the real source path so the error
                // points at the actual file the developer owns.
                $errorOutput = str_replace($tmp, $asset->sourcePath, $errorOutput);

                $this->logger->warning(sprintf(
                    '[AssetMinifier] esbuild could not minify "%s" — serving unminified.%s',
                    $asset->sourcePath,
                    $errorOutput ? "\n" . $errorOutput : ' (no error details from esbuild)',
                ));

                // Always degrade gracefully; a minification failure must never
                // block the rest of the build.
                return $content;
            }

            return $process->getOutput();
        } finally {
            @unlink($tmp);
        }
    }
}
