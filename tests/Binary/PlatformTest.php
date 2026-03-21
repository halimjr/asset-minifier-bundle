<?php

namespace HalimJr\AssetMinifierBundle\Tests\Binary;

use HalimJr\AssetMinifierBundle\Binary\Platform;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
    public function testDetectReturnsString(): void
    {
        // Should not throw on any supported platform
        $platform = Platform::detect();
        $this->assertNotEmpty($platform);
        $this->assertMatchesRegularExpression('/^(linux|darwin|win32)-(x64|arm64)$/', $platform);
    }

    public function testBinaryFilename(): void
    {
        $filename = Platform::binaryFilename();
        $this->assertContains($filename, ['esbuild', 'esbuild.exe']);
    }

    public function testBinaryPathInTar(): void
    {
        $path = Platform::binaryPathInTar();
        $this->assertStringStartsWith('package/', $path);
        $this->assertStringContainsString('esbuild', $path);
    }
}
