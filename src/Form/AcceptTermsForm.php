<?php declare(strict_types=1);

namespace Guest\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class AcceptTermsForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'guest_agreed_terms',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'I agree with terms and conditions.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_agreed_terms',
                    'required' => !empty($this->getOption('forced')),
                ],
            ])
        ;
    }
}
