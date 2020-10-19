<?php declare(strict_types=1);
namespace Guest\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class EmailForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:email',
                'type' => Element\Email::class,
                'options' => [
                    'label' => 'Email', // @translate
                ],
                'attributes' => [
                    'id' => 'email',
                    'required' => true,
                ],
            ])
        ;
    }
}
