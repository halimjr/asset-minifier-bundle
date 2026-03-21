<?php

namespace HalimJr\AssetMinifierBundle;

use HalimJr\AssetMinifierBundle\DependencyInjection\AssetMinifierExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AssetMinifierBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new AssetMinifierExtension();
    }
}
