<?php

namespace HalimJr\AssetMinifierBundle\DependencyInjection;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use HalimJr\AssetMinifierBundle\Command\DownloadEsbuildCommand;
use HalimJr\AssetMinifierBundle\Compiler\EsbuildCompiler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Parameter;

class AssetMinifierExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $binaryDir = $config['binary_path']
            ?? ('%kernel.project_dir%' . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'esbuild');

        // EsbuildBinary service
        $binaryDef = new Definition(EsbuildBinary::class, [
            '$binaryDir' => $binaryDir,
            '$version'   => $config['esbuild_version'],
        ]);
        $container->setDefinition(EsbuildBinary::class, $binaryDef);

        // EsbuildCompiler service — runs last in the asset compiler chain
        $compilerDef = new Definition(EsbuildCompiler::class, [
            '$binary'      => $binaryDef,
            '$sourceMaps'  => $config['source_maps'],
            '$debug'       => new Parameter('kernel.debug'),
        ]);
        $compilerDef->addTag('asset_mapper.compiler', ['priority' => -100]);
        $container->setDefinition(EsbuildCompiler::class, $compilerDef);

        // Console command
        $commandDef = new Definition(DownloadEsbuildCommand::class, [
            '$binary' => $binaryDef,
        ]);
        $commandDef->addTag('console.command');
        $container->setDefinition(DownloadEsbuildCommand::class, $commandDef);
    }

    public function getAlias(): string
    {
        return 'asset_minifier';
    }
}
