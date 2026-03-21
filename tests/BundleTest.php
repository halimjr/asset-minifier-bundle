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
}
