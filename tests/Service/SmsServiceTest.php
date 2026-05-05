<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SmsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twilio\Rest\Client;

final class SmsServiceTest extends TestCase
{
    private SmsService $service;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SmsService(
            'test_sid',
            'test_token',
            '+21611111111',
            $this->logger
        );
    }

    public function testSendSmsReturnsFalseWhenPhoneNumberIsEmpty(): void
    {
        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->service->sendSms('', 'Hello');

        $this->assertFalse($result);
    }

    public function testSendSmsReturnsTrueWhenSmsIsSent(): void
    {
        $messages = new FakeMessagesApi();
        $twilio = $this->createTwilioClientWithMessages($messages);

        $this->replaceTwilioClient($twilio);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'SMS sent',
                $this->callback(function (array $context): bool {
                    $this->assertSame('+21698765432', $context['to']);
                    $this->assertArrayHasKey('message', $context);
                    return true;
                })
            );

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->service->sendSms('+21698765432', 'Hello from PHPUnit');

        $this->assertTrue($result);
        $this->assertCount(1, $messages->calls);
        $this->assertSame('+21698765432', $messages->calls[0]['phoneNumber']);
        $this->assertSame('+21611111111', $messages->calls[0]['data']['from']);
        $this->assertSame('Hello from PHPUnit', $messages->calls[0]['data']['body']);
    }

    public function testSendSmsReturnsFalseWhenTwilioThrowsException(): void
    {
        $messages = new FakeMessagesApi(true);
        $twilio = $this->createTwilioClientWithMessages($messages);

        $this->replaceTwilioClient($twilio);

        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'SMS failed',
                $this->callback(function (array $context): bool {
                    $this->assertSame('+21698765432', $context['to']);
                    $this->assertSame('Twilio error', $context['error']);
                    return true;
                })
            );

        $result = $this->service->sendSms('+21698765432', 'Hello from PHPUnit');

        $this->assertFalse($result);
    }

    private function createTwilioClientWithMessages(FakeMessagesApi $messages): Client
    {
        return new class($messages) extends Client {
            public FakeMessagesApi $messages;

            public function __construct(FakeMessagesApi $messages)
            {
                $this->messages = $messages;
            }
        };
    }

    private function replaceTwilioClient(Client $twilio): void
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('twilio');
        $property->setValue($this->service, $twilio);
    }
}

final class FakeMessagesApi
{
    public array $calls = [];

    public function __construct(
        private bool $shouldThrow = false
    ) {
    }

    public function create(string $phoneNumber, array $data): void
    {
        if ($this->shouldThrow) {
            throw new \Exception('Twilio error');
        }

        $this->calls[] = [
            'phoneNumber' => $phoneNumber,
            'data' => $data,
        ];
    }
}