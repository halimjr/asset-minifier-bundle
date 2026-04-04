<?php

namespace HalimJr\AssetMinifierBundle\Tests\Compiler;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use HalimJr\AssetMinifierBundle\Compiler\EsbuildCompiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

class EsbuildCompilerTest extends TestCase
{
    private function asset(string $path, bool $isVendor = false): MappedAsset
    {
        return new MappedAsset(
            logicalPath: $path,
            sourcePath: '/project/assets/' . $path,
            publicPathWithoutDigest: $path,
            isVendor: $isVendor,
        );
    }

    private function binaryMock(bool $available = true): EsbuildBinary
    {
        $mock = $this->createMock(EsbuildBinary::class);
        $mock->method('isAvailable')->willReturn($available);
        $mock->method('getPath')->willReturn('/nonexistent/esbuild');

        return $mock;
    }

    // supports()

    public function testSupportsNonVendorJs(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock());

        $this->assertTrue($compiler->supports($this->asset('app/main.js')));
    }

    public function testDoesNotSupportVendorJs(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock());

        $this->assertFalse($compiler->supports($this->asset('vendor/lib.js', isVendor: true)));
    }

    public function testDoesNotSupportImageAsset(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock());

        $this->assertFalse($compiler->supports($this->asset('app/logo.png')));
    }

    public function testSupportsCssAsset(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock());

        $this->assertTrue($compiler->supports($this->asset('app/style.css')));
    }

    public function testDoesNotSupportVendorCss(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock());

        $this->assertFalse($compiler->supports($this->asset('vendor/lib.css', isVendor: true)));
    }

    public function testDoesNotSupportCssWhenMinifyCssDisabled(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock(), minifyCss: false);

        $this->assertFalse($compiler->supports($this->asset('app/style.css')));
    }

    public function testDoesNotSupportJsWhenMinifyJsDisabled(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock(), minifyJs: false);

        $this->assertFalse($compiler->supports($this->asset('app/main.js')));
    }


    // compile() — binary init

    public function testDownloadsBinaryWhenNotAvailable(): void
    {
        $binary = $this->createMock(EsbuildBinary::class);
        $binary->method('isAvailable')->willReturn(false);
        $binary->method('getPath')->willReturn('/nonexistent/esbuild');
        $binary->expects($this->once())->method('download');

        $compiler = new EsbuildCompiler($binary, debug: false);
        $compiler->compile('x', $this->asset('app/main.js'), $this->createStub(AssetMapperInterface::class));
    }

    public function testDoesNotDownloadWhenAlreadyAvailable(): void
    {
        $binary = $this->createMock(EsbuildBinary::class);
        $binary->method('isAvailable')->willReturn(true);
        $binary->method('getPath')->willReturn('/nonexistent/esbuild');
        $binary->expects($this->never())->method('download');

        $compiler = new EsbuildCompiler($binary, debug: false);
        $compiler->compile('x', $this->asset('app/main.js'), $this->createStub(AssetMapperInterface::class));
    }

    public function testInitializesOnlyOnce(): void
    {
        $binary = $this->createMock(EsbuildBinary::class);
        $binary->method('isAvailable')->willReturn(false);
        $binary->method('getPath')->willReturn('/nonexistent/esbuild');
        $binary->expects($this->once())->method('download');

        $compiler = new EsbuildCompiler($binary, debug: false);
        $asset  = $this->asset('app/main.js');
        $mapper = $this->createStub(AssetMapperInterface::class);

        $compiler->compile('a', $asset, $mapper);
        $compiler->compile('b', $asset, $mapper);
        $compiler->compile('c', $asset, $mapper);
    }

    // compile() — process failure

    public function testReturnsOriginalContentOnProcessFailure(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock(), debug: false);
        $content  = 'console.log("hello")';

        $result = $compiler->compile(
            $content,
            $this->asset('app/main.js'),
            $this->createStub(AssetMapperInterface::class)
        );

        $this->assertSame($content, $result);
    }

    public function testThrowsInDebugModeOnProcessFailure(): void
    {
        $compiler = new EsbuildCompiler($this->binaryMock(), debug: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/esbuild failed to minify/');

        $compiler->compile(
            'console.log(1)',
            $this->asset('app/main.js'),
            $this->createStub(AssetMapperInterface::class)
        );
    }
}
