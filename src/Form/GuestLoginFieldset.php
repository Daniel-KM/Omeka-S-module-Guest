<?php declare(strict_types=1);

namespace Guest\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class GuestLoginFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][show_login_form]',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Display login form', // @translate
                    'value_options' => [
                        '' => 'Use site setttings', // @translate
                        'no' => 'No', // @translate
                        'yes' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest_show_login_form',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][disable_trigger]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Disable trigger', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_disable_trigger',
                ],
            ])
        ;
    }
}
