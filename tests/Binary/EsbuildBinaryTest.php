<?php

namespace HalimJr\AssetMinifierBundle\Tests\Binary;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use PHPUnit\Framework\TestCase;

class EsbuildBinaryTest extends TestCase
{
    public function testGetPathContainsVersion(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2');
        $this->assertStringContainsString('0.25.2', $binary->getPath());
    }

    public function testGetPathContainsBinaryFilename(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2');
        $this->assertStringContainsString('esbuild', $binary->getPath());
    }

    public function testIsAvailableReturnsFalseForNonExistentPath(): void
    {
        $binary = new EsbuildBinary('/nonexistent/path', '0.25.2');
        $this->assertFalse($binary->isAvailable());
    }

    public function testGetVersion(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2');
        $this->assertSame('0.25.2', $binary->getVersion());
    }

    public function testDefaultVersion(): void
    {
        $binary = new EsbuildBinary('/some/dir');
        $this->assertSame(EsbuildBinary::DEFAULT_VERSION, $binary->getVersion());
    }

    public function testGetDownloadUrl(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2');
        $url = $binary->getDownloadUrl();
        $this->assertStringStartsWith('https://registry.npmjs.org/@esbuild/', $url);
        $this->assertStringContainsString('0.25.2', $url);
    }

    public function testCustomBinaryPathOverridesGetPath(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2', '/usr/local/bin/esbuild');
        $this->assertSame('/usr/local/bin/esbuild', $binary->getPath());
    }

    public function testIsManagedReturnsTrueByDefault(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2');
        $this->assertTrue($binary->isManaged());
    }

    public function testIsManagedReturnsFalseWhenCustomPathSet(): void
    {
        $binary = new EsbuildBinary('/some/dir', '0.25.2', '/usr/local/bin/esbuild');
        $this->assertFalse($binary->isManaged());
    }
}
