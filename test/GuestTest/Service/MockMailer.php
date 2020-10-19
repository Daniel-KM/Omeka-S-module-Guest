<?php declare(strict_types=1);

namespace GuestTest\Service;

use Laminas\Mail\Message;
use Omeka\Stdlib\Mailer;

class MockMailer extends Mailer
{
    protected $message;

    public function send($message): void
    {
        if ($message instanceof Message) {
            $this->message = $message;
        } else {
            $this->message = $this->createMessage($message);
        }
    }

    public function getMessage()
    {
        return $this->message;
    }
}
