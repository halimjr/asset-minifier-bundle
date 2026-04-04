<?php

namespace HalimJr\AssetMinifierBundle\Compiler;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Process\Process;

/**
 * Minifies JavaScript and CSS assets using esbuild.
 *
 * Registered with priority -100 so it always runs after Symfony's
 * JavaScriptImportPathCompiler, which must resolve import paths first.
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
                '--log-level=silent',
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
                if ($this->debug) {
                    throw new \RuntimeException(sprintf(
                        'esbuild failed to minify "%s": %s',
                        $asset->sourcePath,
                        trim($process->getErrorOutput()) ?: 'unknown error'
                    ));
                }

                // In production, degrade gracefully rather than breaking the build.
                return $content;
            }

            return $process->getOutput();
        } finally {
            @unlink($tmp);
        }
    }
}
