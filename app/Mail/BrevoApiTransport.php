<?php

namespace App\Mail;

use GuzzleHttp\Client;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class BrevoApiTransport implements TransportInterface
{
    private string $apiKey;
    private Client $client;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.brevo.com',
            'timeout' => 30,
        ]);
    }

    public function send(\Symfony\Component\Mime\RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $sentMessage = new SentMessage($message, $envelope ?? new Envelope(
            new Address('noreply@aduanefie.com', 'Aduanefie'),
            $message instanceof Email ? $message->getFrom() : []
        ));

        try {
            $email = MessageConverter::toEmail($message instanceof SentMessage ? $message->getOriginalMessage() : $message);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to convert message to Email: ' . $e->getMessage(), 0, $e);
        }

        $envelope = $sentMessage->getEnvelope();

        $recipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $recipients[] = ['email' => $recipient->getAddress(), 'name' => $recipient->getName() ?: ''];
        }

        $from = $email->getFrom();
        $fromAddress = reset($from);

        $payload = [
            'sender' => [
                'name' => $fromAddress ? $fromAddress->getName() : 'Aduanefie',
                'email' => $fromAddress ? $fromAddress->getAddress() : 'noreply@aduanefie.com',
            ],
            'to' => $recipients,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(fn($a) => ['email' => $a->getAddress(), 'name' => $a->getName() ?: ''], $cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map(fn($a) => ['email' => $a->getAddress(), 'name' => $a->getName() ?: ''], $bcc);
        }

        $response = $this->client->post('/v3/smtp/email', [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() >= 400) {
            $body = json_decode($response->getBody()->getContents(), true);
            throw new \RuntimeException('Brevo API error: ' . ($body['message'] ?? 'Unknown error'));
        }

        return $sentMessage;
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}
