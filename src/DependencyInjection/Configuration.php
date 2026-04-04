<?php

namespace HalimJr\AssetMinifierBundle\DependencyInjection;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('asset_minifier');

        $tree->getRootNode()
            ->children()
                ->scalarNode('esbuild_version')
                    ->defaultValue(EsbuildBinary::DEFAULT_VERSION)
                    ->info('The esbuild version to download. Example: "0.25.2".')
                    ->validate()
                        ->ifTrue(static fn (string $v) => !preg_match('/^\d+\.\d+\.\d+$/', $v))
                        ->thenInvalid('Invalid version "%s". Expected format: "X.Y.Z" (e.g. "0.25.2").')
                    ->end()
                ->end()
                ->scalarNode('binary_path')
                    ->defaultNull()
                    ->info('Absolute path to a pre-existing esbuild binary. Disables automatic downloading.')
                ->end()
                ->booleanNode('source_maps')
                    ->defaultFalse()
                    ->info('Embed source maps inline in minified output. Useful for production debugging.')
                ->end()
                ->booleanNode('minify_js')
                    ->defaultTrue()
                    ->info('Minify JavaScript assets. Disable to skip JS minification.')
                ->end()
                ->booleanNode('minify_css')
                    ->defaultTrue()
                    ->info('Minify CSS assets. Disable to skip CSS minification.')
                ->end()
            ->end()
        ;

        return $tree;
    }
}
