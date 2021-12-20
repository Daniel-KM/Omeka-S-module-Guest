<?php declare(strict_types=1);

namespace Guest\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'guest_reset_agreement_terms',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Reset terms agreement for all guests', // @translate
                    'info' => 'When terms and conditions are updated, you may want guests agree them one more time. Warning: to set false will impact all guests. So warn them some time before.', // @translate
                    'value_options' => [
                        'keep' => 'No change', // @translate
                        'unset' => 'Set false', // @translate
                        'set' => 'Set true', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest-reset-agreement-terms',
                    'value' => 'keep',
                    'required' => false,
                ],
            ])
        ;
    }
}
