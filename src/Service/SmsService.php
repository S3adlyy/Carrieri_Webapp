<?php

namespace App\Service;

use Twilio\Rest\Client;
use Psr\Log\LoggerInterface;

class SmsService
{
    private Client $twilio;
    private string $fromNumber;
    private LoggerInterface $logger;

    public function __construct(
        string $twilioSid,
        string $twilioAuthToken,
        string $twilioPhoneNumber,
        LoggerInterface $logger
    ) {
        $this->twilio = new Client($twilioSid, $twilioAuthToken);
        $this->fromNumber = $twilioPhoneNumber;
        $this->logger = $logger;
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        if (empty($phoneNumber)) {
            return false;
        }

        try {
            $this->twilio->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );

            $this->logger->info('SMS sent', [
                'to' => $phoneNumber,
                'message' => substr($message, 0, 50) . '...'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('SMS failed', [
                'to' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}