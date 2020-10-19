<?php

namespace GuestTest\Service;

use Omeka\Stdlib\Mailer;
use Laminas\Mail\Message;

class MockMailer extends Mailer
{
    protected $message;

    public function send($message)
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
