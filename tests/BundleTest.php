<?php

namespace HalimJr\AssetMinifierBundle\Tests;

use HalimJr\AssetMinifierBundle\AssetMinifierBundle;
use HalimJr\AssetMinifierBundle\DependencyInjection\AssetMinifierExtension;
use PHPUnit\Framework\TestCase;

class BundleTest extends TestCase
{
    public function testBundleInstantiates(): void
    {
        $bundle = new AssetMinifierBundle();
        $this->assertInstanceOf(AssetMinifierBundle::class, $bundle);
    }

    public function testBundleReturnsExtension(): void
    {
        $bundle = new AssetMinifierBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertInstanceOf(AssetMinifierExtension::class, $extension);
    }

    public function testExtensionAlias(): void
    {
        $extension = new AssetMinifierExtension();
        $this->assertSame('asset_minifier', $extension->getAlias());
    }

    public function testConfigurationDefaults(): void
    {
        $configuration = new \HalimJr\AssetMinifierBundle\DependencyInjection\Configuration();
        $processor = new \Symfony\Component\Config\Definition\Processor();
        $config = $processor->processConfiguration($configuration, []);

        $this->assertSame(\HalimJr\AssetMinifierBundle\Binary\EsbuildBinary::DEFAULT_VERSION, $config['esbuild_version']);
        $this->assertNull($config['binary_path']);
        $this->assertFalse($config['source_maps']);
        $this->assertTrue($config['minify_js']);
        $this->assertTrue($config['minify_css']);
    }

    public function testCompilerBuildsWithoutLoggerService(): void
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        (new AssetMinifierExtension())->load([], $container);
        $container->getDefinition(\HalimJr\AssetMinifierBundle\Compiler\EsbuildCompiler::class)->setPublic(true);
        $container->compile();

        $this->assertInstanceOf(
            \HalimJr\AssetMinifierBundle\Compiler\EsbuildCompiler::class,
            $container->get(\HalimJr\AssetMinifierBundle\Compiler\EsbuildCompiler::class)
        );
    }
}
