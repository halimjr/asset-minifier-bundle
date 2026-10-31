<?php

namespace HalimJr\AssetMinifierBundle\Tests\Command;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use HalimJr\AssetMinifierBundle\Command\DownloadEsbuildCommand;
use HalimJr\AssetMinifierBundle\Exception\BinaryDownloadException;
use HalimJr\AssetMinifierBundle\Exception\PlatformNotSupportedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DownloadEsbuildCommandTest extends TestCase
{
    private function binary(bool $available = true, bool $managed = true, bool $mock = false): EsbuildBinary
    {
        $binary = $mock ? $this->createMock(EsbuildBinary::class) : $this->createStub(EsbuildBinary::class);
        $binary->method('isAvailable')->willReturn($available);
        $binary->method('isManaged')->willReturn($managed);
        $binary->method('getPath')->willReturn('/var/esbuild/0.25.2/esbuild');
        $binary->method('getVersion')->willReturn('0.25.2');
        $binary->method('getDownloadUrl')->willReturn('https://registry.npmjs.org/@esbuild/linux-x64/-/linux-x64-0.25.2.tgz');

        return $binary;
    }

    private function tester(EsbuildBinary $binary): CommandTester
    {
        return new CommandTester(new DownloadEsbuildCommand($binary));
    }

    // managed binary

    public function testReturnsSuccessWhenAlreadyDownloaded(): void
    {
        $tester = $this->tester($this->binary(available: true));
        $code   = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('already downloaded', $tester->getDisplay());
    }

    public function testDownloadsBinaryAndReturnsSuccess(): void
    {
        $binary = $this->binary(available: false, mock: true);
        $binary->expects($this->once())->method('download');

        $this->assertSame(Command::SUCCESS, $this->tester($binary)->execute([]));
    }

    public function testReturnsFailureOnDownloadException(): void
    {
        $binary = $this->binary(available: false);
        $binary->method('download')->willThrowException(new BinaryDownloadException('network error'));

        $tester = $this->tester($binary);
        $code   = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('network error', $tester->getDisplay());
    }

    public function testReturnsFailureOnPlatformException(): void
    {
        $binary = $this->binary(available: false);
        $binary->method('download')->willThrowException(new PlatformNotSupportedException('unsupported'));

        $tester = $this->tester($binary);
        $code   = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('unsupported', $tester->getDisplay());
    }

    // custom binary_path

    public function testReturnsSuccessForAvailableCustomPath(): void
    {
        $tester = $this->tester($this->binary(available: true, managed: false));
        $code   = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('pre-configured binary', $tester->getDisplay());
    }

    public function testReturnsFailureForMissingCustomPath(): void
    {
        $tester = $this->tester($this->binary(available: false, managed: false));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
    }

    // dry-run

    public function testDryRunShowsPreviewWhenNotDownloaded(): void
    {
        $tester = $this->tester($this->binary(available: false));
        $tester->execute(['--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('0.25.2', $display);
        $this->assertStringContainsString('Not yet downloaded', $display);
    }

    public function testDryRunShowsAlreadyDownloaded(): void
    {
        $tester = $this->tester($this->binary(available: true));
        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('Already downloaded', $tester->getDisplay());
    }
}
