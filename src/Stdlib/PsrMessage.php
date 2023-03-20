<?php declare(strict_types=1);

namespace Guest\Stdlib;

use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorAwareTrait;

/**
 * Manage a message with a context list of placeholders formatted as psr-3.
 *
 * Copy of Omeka Message, except the constructor, that requires an array, and
 * the possibility to translate automatically when the translator is enabled.
 * Generally, the translator is not set, as it is usually managed internally.
 *
 * ```
 * // To get a translator in a controller:
 * $translator = $this->getEvent()->getApplication()->getServiceManager()->get('MvcTranslator');
 * // or:
 * $translator = $this->viewHelpers()->get('translate')->getTranslator();
 *
 * // To get translator in a view:
 * $translator = $this->plugin('translate')->getTranslator();
 *
 * // To set the translator:
 * $psrMessage->setTranslator($translator);
 * // To disable the translation when the translator is set:
 * $psrMessage->setTranslatorEnabled(false);
 * ```
 *
 * Should not be an extension of \Omeka\Stdlib\Message currently, because
 * another delegator cannot be set for the translator simply.
 * So when the PsrMessage is used in uncommon places (not with messenger or
 * logs), and as long as \Omeka\I18n\Translator doesn't manage PSR-3, the
 * message is interpolated directly, with translation if possible.
 *
 * @todo Move PsrMessage to its own module or in core.
 *
 * @see \Omeka\Stdlib\Message
 */
class PsrMessage implements \JsonSerializable, PsrInterpolateInterface, TranslatorAwareInterface
{
    use PsrInterpolateTrait;
    use TranslatorAwareTrait;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $context = [];

    /**
     * @var bool
     */
    protected $escapeHtml = true;

    /**
     * Set the message string and its context. The plural is not managed.
     */
    public function __construct($message, array $context = [])
    {
        $this->message = $message;
        $this->context = $context;
    }

    /**
     * Get the message string.
     */
    public function getMessage(): string
    {
        return (string) $this->message;
    }

    /**
     * Get the message context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Does this message have context?
     */
    public function hasContext(): bool
    {
        return (bool) $this->context;
    }

    /**
     * Get the message arguments for compatibility purpose only.
     *
     * @deprecated Use hasContext() instead.
     * @return array Non-associative array in order to comply with sprintf.
     */
    public function getArgs()
    {
        return array_values($this->getContext());
    }

    /**
     * Does this message have arguments? For compatibility purpose only.
     *
     * @deprecated Use hasContext() instead.
     * @return bool
     */
    public function hasArgs()
    {
        return $this->hasContext();
    }

    public function setEscapeHtml($escapeHtml): self
    {
        $this->escapeHtml = (bool) $escapeHtml;
        return $this;
    }

    public function escapeHtml(): bool
    {
        return $this->escapeHtml;
    }

    public function __toString()
    {
        return $this->isTranslatorEnabled()
            ? $this->translate()
            : $this->interpolate($this->getMessage(), $this->getContext());
    }

    /**
     * Translate the message with the context.
     *
     * Same as TranslatorInterface::translate(), but the message is the current one.
     */
    public function translate($textDomain = 'default', $locale = null): string
    {
        return $this->hasTranslator()
            ? $this->interpolate($this->translator->translate($this->getMessage(), $textDomain, $locale), $this->getContext())
            : $this->interpolate($this->getMessage(), $this->getContext());
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}
