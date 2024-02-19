<?php declare(strict_types=1);

namespace Guest\Form;

use Laminas\EventManager\Event;

class UserForm extends \Omeka\Form\UserForm
{
    public function init(): void
    {
        // This form is used on public side only, not via api or admin.

        if (empty($this->options['is_public'])) {
            parent::init();
            return;
        }

        // Copy of all non-admin settings.

        $this->add([
            'name' => 'user-information',
            'type' => 'fieldset',
        ]);
        $this->add([
            'name' => 'user-settings',
            'type' => 'fieldset',
        ]);
        $this->add([
            'name' => 'change-password',
            'type' => 'fieldset',
        ]);
        $this->add([
            'name' => 'edit-keys',
            'type' => 'fieldset',
        ]);
        $this->get('user-information')->add([
            'name' => 'o:email',
            'type' => 'Email',
            'options' => [
                'label' => 'Email', // @translate
            ],
            'attributes' => [
                'id' => 'email',
                'required' => true,
            ],
        ]);
        $this->get('user-information')->add([
            'name' => 'o:name',
            'type' => 'Text',
            'options' => [
                'label' => 'Display name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        $userId = $this->getOption('user_id');
        $locale = $userId ? $this->userSettings->get('locale', null, $userId) : null;
        if (null === $locale) {
            $locale = $this->settings->get('locale');
        }

        $settingsFieldset = $this->get('user-settings');
        $settingsFieldset->setOption('element_groups', [
            'columns' => 'Admin browse columns', // @translate
            'browse_defaults' => 'Admin browse defaults', // @translate
        ]);
        $settingsFieldset->add([
            'name' => 'locale',
            'type' => 'Omeka\Form\Element\LocaleSelect',
            'options' => [
                'label' => 'Locale', // @translate
                // 'info' => 'Global locale/language code for all interfaces.', // @translate
            ],
            'attributes' => [
                'value' => $locale,
                'class' => 'chosen-select',
                'id' => 'locale',
            ],
        ]);

        if ($this->getOption('include_password')) {
            if ($this->getOption('current_password')) {
                $this->get('change-password')->add([
                    'name' => 'current-password',
                    'type' => 'password',
                    'options' => [
                        'label' => 'Current password', // @translate
                    ],
                    'attributes' => [
                        'id' => 'current-password',
                    ],
                ]);
            }
            $this->get('change-password')->add([
                'name' => 'password-confirm',
                'type' => 'Omeka\Form\Element\PasswordConfirm',
            ]);
            $this->get('change-password')->get('password-confirm')->setLabels(
                'New password', // @translate
                'Confirm new password' // @translate
            );
        }

        if ($this->getOption('include_key')) {
            $this->get('edit-keys')->add([
                'name' => 'new-key-label',
                'type' => 'Text',
                'options' => [
                    'label' => 'New key label', // @translate
                ],
                'attributes' => [
                    'id' => 'new-key-label',
                ],
            ]);
        }

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        // separate input filter stuff so that the event work right
        $inputFilter = $this->getInputFilter();

        $inputFilter->get('user-settings')->add([
            'name' => 'locale',
            'allow_empty' => true,
        ]);

        if ($this->getOption('include_key')) {
            $inputFilter->get('edit-keys')->add([
                'name' => 'new-key-label',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255,
                        ],
                    ],
                ],
            ]);
        }

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}
