<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\S3Service;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class S3ServiceTest extends TestCase
{
    private S3Service $service;
    private S3Client $client;

    protected function setUp(): void
    {
        $this->service = new S3Service('eu-west-3', 'test-bucket');

        /** @var S3Client&MockObject $client */
        $client = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCommand', 'createPresignedRequest'])
            ->addMethods(['putObject', 'getObject', 'headObject', 'deleteObject'])
            ->getMock();

        $this->client = $client;

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('client');
        $property->setValue($this->service, $this->client);
    }
    public function testUploadFileUsesGivenContentTypeAndReturnsKey(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 's3_test_');
        file_put_contents($tmpFile, 'hello');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'hello.txt',
            'text/plain',
            null,
            true
        );

        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'docs/hello.txt',
                'SourceFile' => $uploadedFile->getRealPath(),
                'ContentType' => 'text/plain',
            ])
            ->willReturn(new Result([]));

        $result = $this->service->uploadFile($uploadedFile, 'docs/hello.txt', 'text/plain');

        $this->assertSame('docs/hello.txt', $result);

        @unlink($tmpFile);
    }

    public function testUploadBodyReturnsKey(): void
    {
        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'notes/test.txt',
                'Body' => 'abc',
                'ContentType' => 'text/plain',
            ])
            ->willReturn(new Result([]));

        $result = $this->service->uploadBody('notes/test.txt', 'abc');

        $this->assertSame('notes/test.txt', $result);
    }

    public function testDownloadBytesReturnsBodyContent(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'file.txt',
            ])
            ->willReturn(new Result([
                'Body' => 'file-content',
            ]));

        $result = $this->service->downloadBytes('file.txt');

        $this->assertSame('file-content', $result);
    }

    public function testDownloadToTempFileReturnsSavedFilePath(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getObject')
            ->with($this->callback(function (array $args): bool {
                $this->assertSame('test-bucket', $args['Bucket']);
                $this->assertSame('archive.zip', $args['Key']);
                $this->assertArrayHasKey('SaveAs', $args);

                file_put_contents($args['SaveAs'], 'saved-content');

                return true;
            }))
            ->willReturn(new Result([]));

        $tmpPath = $this->service->downloadToTempFile('archive.zip');

        $this->assertFileExists($tmpPath);
        $this->assertSame('saved-content', file_get_contents($tmpPath));

        @unlink($tmpPath);
    }

    public function testPresignedDownloadUrlReturnsUriString(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $this->client
            ->expects($this->once())
            ->method('getCommand')
            ->with('GetObject', [
                'Bucket' => 'test-bucket',
                'Key' => 'report.pdf',
            ])
            ->willReturn($command);

        $this->client
            ->expects($this->once())
            ->method('createPresignedRequest')
            ->with($command, '+600 seconds')
            ->willReturn($request);

        $request
            ->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        $uri
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('https://example.com/download-url');

        $result = $this->service->presignedDownloadUrl('report.pdf');

        $this->assertSame('https://example.com/download-url', $result);
    }

    public function testPresignedUploadUrlReturnsUriString(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $this->client
            ->expects($this->once())
            ->method('getCommand')
            ->with('PutObject', [
                'Bucket' => 'test-bucket',
                'Key' => 'upload.png',
                'ContentType' => 'image/png',
            ])
            ->willReturn($command);

        $this->client
            ->expects($this->once())
            ->method('createPresignedRequest')
            ->with($command, '+300 seconds')
            ->willReturn($request);

        $request
            ->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        $uri
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('https://example.com/upload-url');

        $result = $this->service->presignedUploadUrl('upload.png', 'image/png');

        $this->assertSame('https://example.com/upload-url', $result);
    }

    public function testExistsReturnsTrueWhenHeadObjectSucceeds(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'existing-file.txt',
            ])
            ->willReturn(new Result([]));

        $this->assertTrue($this->service->exists('existing-file.txt'));
    }

    public function testExistsReturnsFalseWhenS3ExceptionIsThrown(): void
    {
        $command = $this->createMock(CommandInterface::class);

        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'missing-file.txt',
            ])
            ->willThrowException(new S3Exception('Not found', $command));

        $this->assertFalse($this->service->exists('missing-file.txt'));
    }

    public function testDeleteCallsDeleteObject(): void
    {
        $this->client
            ->expects($this->once())
            ->method('deleteObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'old-file.txt',
            ])
            ->willReturn(new Result([]));

        $this->service->delete('old-file.txt');

        $this->addToAssertionCount(1);
    }

    public function testGetBucketReturnsBucket(): void
    {
        $this->assertSame('test-bucket', $this->service->getBucket());
    }

    public function testGetRegionReturnsRegion(): void
    {
        $this->assertSame('eu-west-3', $this->service->getRegion());
    }
}