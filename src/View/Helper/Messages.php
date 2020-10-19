<?php declare(strict_types=1);
namespace Guest\View\Helper;

use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * View helper for proxing the messenger controller plugin.
 *
 * Replace Omeka core Messages in order to manage PsrMessage too.
 * @see \Omeka\View\Helper\Messages
 *
 * @todo Move PsrMessage to its own module or in core.
 */
class Messages extends AbstractHelper
{
    /**
     * Get all messages and clear them from the session.
     *
     * @return array
     */
    public function get()
    {
        $messenger = new Messenger;
        $messages = $messenger->get();
        $messenger->clear();
        return $messages;
    }

    /**
     * Render the messages.
     *
     * @return string
     */
    public function __invoke()
    {
        $allMessages = $this->get();
        if (!$allMessages) {
            return '';
        }

        $view = $this->getView();
        $escape = $view->plugin('escapeHtml');
        $translate = $view->plugin('translate');
        $translator = $translate->getTranslator();
        $output = '<ul class="messages">';
        $typeToClass = [
            Messenger::ERROR => 'error',
            Messenger::SUCCESS => 'success',
            Messenger::WARNING => 'warning',
            Messenger::NOTICE => 'notice',
        ];
        // Most of the time, the messages are a unique and simple string.
        foreach ($allMessages as $type => $messages) {
            $class = isset($typeToClass[$type]) ? $typeToClass[$type] : 'notice';
            foreach ($messages as $message) {
                $escapeHtml = true; // escape HTML by default
                // "instanceof PsrMessage" cannot be used, since it can be
                // another object (PsrMessage from Log or Guest, etc.), as
                // long as it's not in the core or in a specific module.
                if ($message instanceof TranslatorAwareInterface) {
                    $escapeHtml = $message->escapeHtml();
                    $message = $message->setTranslator($translator)->translate();
                } elseif ($message instanceof Message) {
                    $escapeHtml = $message->escapeHtml();
                    $message = $translate($message);
                } else {
                    $message = $translate($message);
                }
                if ($escapeHtml) {
                    $message = $escape($message);
                }
                $output .= sprintf('<li class="%s">%s</li>', $class, $message);
            }
        }
        $output .= '</ul>';
        return $output;
    }
}
