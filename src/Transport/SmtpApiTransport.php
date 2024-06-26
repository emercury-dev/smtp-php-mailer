<?php
namespace Emercury\Smtp\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmtpApiTransport extends AbstractApiTransport
{
    public function __construct(
        #[\SensitiveParameter] private string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/mail/send', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Emercury-Token' => $this->key,
            ],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Emercury server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException(
                'Unable to send an email: '.
                isset($result['status']['details']) ? implode(', ', $result['status']['details']) : '' .
                    sprintf(' (code %s).', $result['status']['code']), $response);
        }

        $sentMessage->setMessageId($result['data']['messageId']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => current($this->formatAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
        ];

        if ($emails = $email->getReplyTo()) {
            $payload['replyTo'] = current($this->formatAddresses($emails));
        }

        if ($email->getHtmlBody()) {
            $payload['contents'][] = [
                'contentType' => 'text/html',
                'content' => $email->getHtmlBody()
            ];
        }

        if ($email->getTextBody()) {
            $payload['contents'][] = [
                'contentType' => 'text/plain',
                'content' => $email->getTextBody()
            ];
        }

        return $payload;
    }

    /**
     * @return list<array{email: string, name?: string}>
     */
    private function formatAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $address) {
            $formattedAddresses[] = $this->formatAddress($address);
        }

        return $formattedAddresses;
    }

    private function formatAddress(Address $address): array
    {
        $formattedAddress = ['email' => $address->getEncodedAddress()];

        if ($address->getName()) {
            $formattedAddress['name'] = $address->getName();
        }

        return $formattedAddress;
    }

    public function __toString(): string
    {
        return sprintf('emercury+api://%s', $this->getEndpoint());
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.smtp.emercury.net').($this->port ? ':'.$this->port : '');
    }
}