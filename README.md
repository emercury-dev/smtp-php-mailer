Emercury Bridge
============

Provides Emercury integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=emercury+api://KEY@default
```

where:
- `KEY` is your Emercury token

---------------
  Getting Started
---------------

```bash
composer require emercury-dev/smtp-php-mailer
```

```php
use Emercury\Smtp\SmtpApiTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

$mailer = new Mailer(new SmtpApiTransport('xxxTokenxxx'));

$email = (new Email())
    ->from(new Address('hello@example.com', 'Hello'))
    ->to(new Address('you@example.com', 'You'))
    ->replyTo(new Address('hello@example.com', 'Hello'))
    ->subject('Hello!')
    ->text('Sending emails is fun!')
    ->html('<p>Sending emails is fun with HTML integration!</p>');

$mailer->send($email);
```