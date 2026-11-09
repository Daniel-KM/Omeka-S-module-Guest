<?php declare(strict_types=1);

namespace GuestTest\Controller;

/**
 * Other modules may add user settings to the user form, like the module Mapping
 * since version 2.3.0. On the public form, the user may not exist yet, and the
 * admin settings should not be displayed.
 */
class OtherModuleUserSettingsTest extends GuestControllerTestCase
{
    protected int $listenerCalls = 0;

    protected bool $withMappingUserSettings = false;

    /**
     * Attach the listener to each new application, since dispatch() resets it.
     */
    public function getApplication()
    {
        $isNew = !$this->application;
        $application = parent::getApplication();
        if ($isNew && $this->withMappingUserSettings) {
            $this->attachMappingUserSettings($application);
        }
        return $application;
    }

    /**
     * Mimic the listener of the module Mapping (Module::addUserSettings()).
     */
    protected function attachMappingUserSettings($application): void
    {
        $application->getServiceManager()->get('SharedEventManager')->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            function (\Laminas\EventManager\Event $event): void {
                ++$this->listenerCalls;
                $form = $event->getTarget();
                $fieldset = $form->get('user-settings');
                $groups = $fieldset->getOption('element_groups', []);
                $groups['mapping'] = 'Mapping';
                $fieldset->setOption('element_groups', $groups);
                foreach (['mapping_basemap_provider', 'mapping_min_zoom', 'mapping_max_zoom', 'mapping_default_bounds'] as $name) {
                    $fieldset->add([
                        'type' => 'text',
                        'name' => $name,
                        'options' => [
                            'element_group' => 'mapping',
                            'label' => $name,
                        ],
                        'attributes' => [
                            // The user does not exist yet on the register page.
                            'value' => $form->getUserSettings()->get($name),
                        ],
                    ]);
                }
            }
        );
    }

    /**
     * Without user, the standard user settings throw, so the next test checks
     * the user settings of the form of the module, not a no-op.
     */
    public function testStandardUserSettingsThrowWithoutUser(): void
    {
        $userSettings = clone $this->getApplication()->getServiceManager()->get('Omeka\Settings\User');
        $userSettings->setTargetId(null);
        $this->expectException(\Omeka\Service\Exception\RuntimeException::class);
        $userSettings->get('mapping_basemap_provider');
    }

    public function testRegisterPageWorksWhenModulesReadUserSettings(): void
    {
        $this->logout();
        $this->withMappingUserSettings = true;

        $this->dispatch('/s/test/guest/register');

        $this->assertResponseStatusCode(200);
        $this->assertGreaterThan(0, $this->listenerCalls, 'The listener of the other module must run.');
        $this->assertStringNotContainsString('no target ID', $this->getResponse()->getContent());
    }

    public function testRegisterPageHidesAdminSettingsOfMapping(): void
    {
        $this->logout();
        $this->withMappingUserSettings = true;

        $this->dispatch('/s/test/guest/register');

        $this->assertResponseStatusCode(200);
        $this->assertGreaterThan(0, $this->listenerCalls, 'The listener of the other module must run.');
        // The names are escaped in the html.
        preg_match_all('~name="([^"]+)"~', $this->getResponse()->getContent(), $matches);
        $names = array_map('html_entity_decode', $matches[1]);
        $this->assertContains('user-settings[guest_agreed_terms]', $names);
        foreach (['mapping_basemap_provider', 'mapping_min_zoom', 'mapping_max_zoom', 'mapping_default_bounds'] as $name) {
            $this->assertNotContains('user-settings[' . $name . ']', $names);
        }
        $this->assertStringNotContainsString('<fieldset id="mapping">', $this->getResponse()->getContent());
    }
}
