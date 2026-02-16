<?php declare(strict_types=1);

namespace GuestTest\Service;

use Laminas\Mail\Message;
use Omeka\Stdlib\Mailer;

/**
 * Mock mailer for testing email sending.
 *
 * Captures all sent messages without actually sending them.
 */
class MockMailer extends Mailer
{
    /**
     * @var Message|null Last sent message.
     */
    protected $message;

    /**
     * @var Message[] All sent messages.
     */
    protected $messages = [];

    /**
     * Capture the message instead of sending it.
     *
     * @param Message|array $message
     */
    public function send($message): void
    {
        if ($message instanceof Message) {
            $this->message = $message;
        } else {
            $this->message = $this->createMessage($message);
        }
        $this->messages[] = $this->message;
    }

    /**
     * Get the last sent message.
     *
     * @return Message|null
     */
    public function getMessage(): ?Message
    {
        return $this->message;
    }

    /**
     * Get all sent messages.
     *
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the number of sent messages.
     *
     * @return int
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * Clear all captured messages.
     */
    public function clearMessages(): void
    {
        $this->message = null;
        $this->messages = [];
    }
}
