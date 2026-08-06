<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/**
 * Sends mail via PHP's built-in mail() function (i.e. the server's local
 * MTA/sendmail binary through php.ini's sendmail_path), instead of opening
 * an outbound SMTP socket. Needed because GoDaddy shared hosting blocks
 * outbound SMTP on 587/465/25 entirely, which breaks Laravel's normal
 * "smtp" transport with a connection timeout regardless of provider.
 */
class PhpNativeMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new TransportException('PhpNativeMailTransport only supports Symfony Mime Email messages.');
        }

        $to = implode(', ', $this->stringifyAddresses($email->getTo()));

        if ($to === '') {
            throw new TransportException('Cannot send email: no "To" address set.');
        }

        $subject = $email->getSubject() ?? '';
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();
        $body = $htmlBody ?: ($textBody ?: '');

        $headers = [];

        $from = $email->getFrom();
        if (!empty($from)) {
            $headers[] = 'From: ' . $from[0]->toString();
        }

        $replyTo = $email->getReplyTo();
        if (!empty($replyTo)) {
            $headers[] = 'Reply-To: ' . implode(', ', $this->stringifyAddresses($replyTo));
        }

        $cc = $email->getCc();
        if (!empty($cc)) {
            $headers[] = 'Cc: ' . implode(', ', $this->stringifyAddresses($cc));
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $htmlBody
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';

        $sent = mail($to, $subject, $body, implode("\r\n", $headers));

        if (!$sent) {
            throw new TransportException('PHP mail() function returned false -- message was not accepted for delivery.');
        }
    }

    public function __toString(): string
    {
        return 'phpmail://default';
    }
}
