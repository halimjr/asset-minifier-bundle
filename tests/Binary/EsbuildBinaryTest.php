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
}
