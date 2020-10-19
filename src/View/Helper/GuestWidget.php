<?php declare(strict_types=1);

namespace Guest\View\Helper;

use Laminas\View\Helper\AbstractHtmlElement;

class GuestWidget extends AbstractHtmlElement
{
    public function __invoke($widget)
    {
        $escape = $this->getView()->plugin('escapeHtml');

        if (is_array($widget)) {
            $attribs = [
                'class' => 'guest-widget-label',
            ];

            $html = '<h2' . $this->htmlAttribs($attribs) . '>';
            $html .= $escape($widget['label']);
            $html .= '</h2>';
            $html .= $widget['content'];

            return $html;
        } else {
            return $widget;
        }
    }
}
