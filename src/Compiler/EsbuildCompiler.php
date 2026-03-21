<?php

namespace HalimJr\AssetMinifierBundle\Compiler;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Process\Process;

/**
 * Minifies JavaScript assets using esbuild.
 *
 * Registered with priority -100 so it always runs after Symfony's
 * JavaScriptImportPathCompiler, which must resolve import paths first.
 */
class EsbuildCompiler implements AssetCompilerInterface
{
    private bool $initialized = false;

    public function __construct(private readonly EsbuildBinary $binary) {}

    public function supports(MappedAsset $asset): bool
    {
        // Skip vendor assets (pre-minified CDN packages) and non-JS files.
        return !$asset->isVendor && 'js' === $asset->publicExtension;
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        if (!$this->initialized) {
            if (!$this->binary->isAvailable()) {
                $this->binary->download();
            }
            $this->initialized = true;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'esbuild_') . '.js';

        try {
            file_put_contents($tmp, $content);

            $process = new Process([
                $this->binary->getPath(),
                '--minify',
                '--format=esm',
                '--log-level=silent',
                $tmp,
            ]);
            $process->setTimeout(30);
            $process->run();

            // On failure, return original content so the build never breaks.
            if (!$process->isSuccessful()) {
                return $content;
            }

            return $process->getOutput();
        } finally {
            @unlink($tmp);
        }
    }
}
