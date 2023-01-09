<?php declare(strict_types=1);
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2023
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Guest;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Guest\Entity\GuestToken;
use Guest\Permissions\Acl;
use Guest\Stdlib\PsrMessage;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Element;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Permissions\Assertion\IsSelfAssertion;
use Omeka\Settings\SettingsInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * {@inheritDoc}
     * @see \Omeka\Module\AbstractModule::onBootstrap()
     * @todo Find the right way to load Guest before other modules in order to add role.
     */
    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $this->addAclRoleAndRules();
    }

    protected function preInstall(): void
    {
        $this->hasOldGuestUser = $this->checkOldGuestUser();
    }

    protected function postInstall(): void
    {
        // Prepare all translations one time.
        $translatables = [
            'guest_login_text',
            'guest_register_text',
            'guest_dashboard_label',
            'guest_capabilities',
            'guest_short_capabilities',
            'guest_message_confirm_email_subject',
            'guest_message_confirm_email',
            'guest_message_confirm_registration_email_subject',
            'guest_message_confirm_registration_email',
            'guest_message_update_email_subject',
            'guest_message_update_email',
            'guest_message_confirm_email_site',
            'guest_message_confirm_register_site',
            'guest_message_confirm_register_moderate_site',
            'guest_terms_text',
        ];
        $config = $this->getConfig()['guest']['settings'];
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');
        $translatables = array_filter(array_map(function ($v) use ($translate, $config) {
            return !empty($config[$v]) ? $translate($config[$v]) : null;
        }, array_combine($translatables, $translatables)));

        $this->manageMainSettings('update', $translatables);
        $this->manageSiteSettings('update', $translatables);

        if ($this->hasOldGuestUser) {
            require_once __DIR__ . '/data/scripts/upgrade_guest_user.php';
        }
    }

    protected function preUninstall(): void
    {
        $this->deactivateGuests();
    }

    /**
     * Add ACL role and rules for this module.
     */
    protected function addAclRoleAndRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // This check allows to add the role "guest" by dependencies without
        // complex process. It avoids issues when the module is disabled too.
        // TODO Find a way to set the role "guest" during init or via Omeka\Service\AclFactory (allowing multiple delegators).
        if (!$acl->hasRole(Acl::ROLE_GUEST)) {
            $acl->addRole(Acl::ROLE_GUEST);
        }
        $acl->addRoleLabel(Acl::ROLE_GUEST, 'Guest'); // @translate

        $settings = $services->get('Omeka\Settings');
        $isOpenRegister = $settings->get('guest_open', 'moderate');
        $this->addRulesForAnonymous($acl, $isOpenRegister);
        $this->addRulesForGuest($acl);
    }

    /**
     * Add ACL rules for sites.
     *
     * @param LaminasAcl $acl
     * @param bool $isOpenRegister
     */
    protected function addRulesForAnonymous(LaminasAcl $acl, $isOpenRegister = 'moderate'): void
    {
        $acl
            ->allow(
                null,
                [\Guest\Controller\Site\AnonymousController::class]
            );
        if ($isOpenRegister !== 'closed') {
            $acl
                ->allow(
                    null,
                    [\Omeka\Entity\User::class],
                    // Change role and Activate user should be set to allow external
                    // logging (ldap, saml, etc.), not only guest registration here.
                    // Internal checks are added in the controller.
                    ['create', 'change-role', 'activate-user']
                )
                ->allow(
                    null,
                    [\Omeka\Api\Adapter\UserAdapter::class],
                    ['create']
                );
        } else {
            $acl
                ->deny(
                    null,
                    [\Guest\Controller\Site\AnonymousController::class],
                    ['register']
                );
        }
    }

    /**
     * Add ACL rules for "guest" role.
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForGuest(LaminasAcl $acl): void
    {
        $roles = $acl->getRoles();
        $acl
            ->allow(
                $roles,
                [\Guest\Controller\Site\GuestController::class]
            )
            ->allow(
                [Acl::ROLE_GUEST],
                [\Omeka\Entity\User::class],
                ['read', 'update', 'change-password'],
                new IsSelfAssertion
            )
            ->allow(
                [Acl::ROLE_GUEST],
                [\Omeka\Api\Adapter\UserAdapter::class],
                ['read', 'update']
            )
            ->deny(
                [Acl::ROLE_GUEST],
                [
                    'Omeka\Controller\Admin\Asset',
                    'Omeka\Controller\Admin\Index',
                    'Omeka\Controller\Admin\Item',
                    'Omeka\Controller\Admin\ItemSet',
                    'Omeka\Controller\Admin\Job',
                    'Omeka\Controller\Admin\Media',
                    'Omeka\Controller\Admin\Module',
                    'Omeka\Controller\Admin\Property',
                    'Omeka\Controller\Admin\ResourceClass',
                    'Omeka\Controller\Admin\ResourceTemplate',
                    'Omeka\Controller\Admin\Setting',
                    'Omeka\Controller\Admin\SystemInfo',
                    'Omeka\Controller\Admin\User',
                    'Omeka\Controller\Admin\Vocabulary',
                    'Omeka\Controller\SiteAdmin\Index',
                    'Omeka\Controller\SiteAdmin\Page',
                ]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // TODO How to attach all public events only?
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'appendLoginNav']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\UserAdapter::class,
            'api.delete.post',
            [$this, 'deleteGuestToken']
        );

        // Add the guest main infos to the user show admin pages.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.details',
            [$this, 'viewUserDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.show.after',
            [$this, 'viewUserShowAfter']
        );

        // Add the guest element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );
        // Add the guest element filters to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_input_filters',
            [$this, 'addUserFormElementFilter']
        );
        // FIXME Use the autoset of the values (in a fieldset) and remove this.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.form.before',
            [$this, 'addUserFormValue']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(\Guest\Form\ConfigForm::class);
        $form->init();
        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $result = parent::handleConfigForm($controller);
        if ($result === false) {
            return false;
        }

        $services = $this->getServiceLocator();
        $params = $controller->getRequest()->getPost();
        switch ($params['guest_reset_agreement_terms']) {
            case 'unset':
                $this->resetAgreementsBySql($services, false);
                $message = new PsrMessage('All guests must agreed the terms one more time.'); // @translate
                $controller->messenger()->addSuccess($message);
                break;
            case 'set':
                $this->resetAgreementsBySql($services, true);
                $message = new PsrMessage('All guests agreed the terms.'); // @translate
                $controller->messenger()->addSuccess($message);
                break;
            default:
                break;
        }
    }

    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null, iterable $values = []): bool
    {
        if ($settingsType !== 'site_settings') {
            return parent::initDataToPopulate($settings, $settingsType, $id, $values);
        }

        $translatables = [
            'guest_login_text',
            'guest_register_text',
            'guest_dashboard_label',
            'guest_capabilities',
            'guest_short_capabilities',
            'guest_message_confirm_email_subject',
            'guest_message_confirm_email',
            'guest_message_confirm_registration_email_subject',
            'guest_message_confirm_registration_email',
            'guest_message_update_email_subject',
            'guest_message_update_email',
            'guest_message_confirm_email_site',
            'guest_message_confirm_register_site',
            'guest_message_confirm_register_moderate_site',
            'guest_terms_text',
        ];
        $config = $this->getConfig()['guest']['site_settings'];
        $translate = $this->getServiceLocator()->get('ControllerPluginManager')->get('translate');
        $translatables = array_filter(array_map(function ($v) use ($translate, $config) {
            return !empty($config[$v]) ? $translate($config[$v]) : null;
        }, array_combine($translatables, $translatables)));

        return parent::initDataToPopulate($settings, $settingsType, $id, $translatables);
    }

    public function appendLoginNav(Event $event): void
    {
        $view = $event->getTarget();
        if ($view->params()->fromRoute('__ADMIN__')) {
            return;
        }
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            $view->headStyle()->appendStyle('li a.registerlink, li a.loginlink { display:none; }');
        } else {
            $view->headStyle()->appendStyle('li a.logoutlink { display:none; }');
        }
    }

    public function viewUserDetails(Event $event): void
    {
        $view = $event->getTarget();
        $user = $view->resource;
        $this->viewUserData($view, $user, 'common/admin/guest');
    }

    public function viewUserShowAfter(Event $event): void
    {
        $view = $event->getTarget();
        $user = $view->vars()->user;
        $this->viewUserData($view, $user, 'common/admin/guest-list');
    }

    protected function viewUserData(PhpRenderer $view, UserRepresentation $user, $template): void
    {
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->id());

        $guestSite = $this->guestSite($user);
        echo $view->partial(
            $template,
            [
                'user' => $user,
                'userSettings' => $userSettings,
                'guestSite' => $guestSite,
            ]
        );
    }

    public function addUserFormElement(Event $event): void
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();
        $services = $this->getServiceLocator();

        $auth = $services->get('Omeka\AuthenticationService');

        $settings = $services->get('Omeka\Settings');
        $skip = $settings->get('guest_terms_skip');

        $isV4 = version_compare(\Omeka\Module::VERSION, '4', '>=');

        if ($isV4) {
            $elementGroups = [
                'guest' => 'Guest', // @translate
            ];
            $userSettingsFieldset = $form->get('user-settings');
            $userSettingsFieldset->setOption('element_groups', array_merge($userSettingsFieldset->getOption('element_groups') ?: [], $elementGroups));
        }

        // Public form.
        if ($form->getOption('is_public') && !$skip) {
            // Don't add the agreement checkbox in public when registered.
            if ($auth->hasIdentity()) {
                return;
            }

            $fieldset = $form->get('user-settings');
            $fieldset
                ->add([
                    'name' => 'guest_agreed_terms',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'element_group' => 'guest',
                        'label' => 'Agreed terms', // @translate
                    ],
                    'attributes' => [
                        'id' => 'guest_agreed_terms',
                        'value' => false,
                        'required' => true,
                    ],
                ]);
            return;
        }

        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);

        // Manage a direct creation (no id).
        if ($user) {
            /** @var \Omeka\Settings\UserSettings $userSettings */
            $userSettings = $services->get('Omeka\Settings\User');
            $userSettings->setTargetId($userId);
            $agreedTerms = $userSettings->get('guest_agreed_terms');
            $siteRegistration = $userSettings->get('guest_site', $settings->get('default_site', 1));
        } else {
            $agreedTerms = false;
            $siteRegistration = $settings->get('default_site', 1);
        }

        // Admin board.
        $fieldset = $form->get('user-settings');
        $fieldset
            ->add([
                'name' => 'guest_site',
                'type' => \Guest\Form\Element\OptionalSiteSelect::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Guest site', // @translate
                    'info' => 'This parameter is used to manage some site related features, in particular messages.', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'guest_site',
                    'class' => 'chosen-select',
                    'value' => $siteRegistration,
                    'required' => false,
                    'multiple' => false,
                    'data-placeholder' => 'Select site…', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_agreed_terms',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Agreed terms', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_agreed_terms',
                    'value' => $agreedTerms,
                ],
            ])
            ->add([
                'name' => 'guest_send_email_moderated_registration',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Send an email to confirm registration after moderation (user should be activated first)', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_send_email_moderated_registration',
                ],
            ]);

        if (!$user) {
            return;
        }

        /** @var \Guest\Entity\GuestToken $guestToken */
        $guestToken = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$guestToken || $guestToken->isConfirmed()) {
            return;
        }

        $fieldset
            ->add([
                'name' => 'guest_clear_token',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Clear registration token', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_clear_token',
                    'value' => false,
                ],
            ]);
    }

    public function addUserFormElementFilter(Event $event): void
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();
        if ($form->getOption('is_public')) {
            return;
        }

        $services = $this->getServiceLocator();
        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('user-settings')
            ->add([
                'name' => 'guest_send_email_moderated_registration',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'sendEmailModeration'],
                        ],
                    ],
                ],
            ]);

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);
        if (!$user) {
            return;
        }

        /** @var \Guest\Entity\GuestToken $guestToken */
        $guestToken = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$guestToken || $guestToken->isConfirmed()) {
            return;
        }

        $inputFilter->get('user-settings')
            ->add([
                'name' => 'guest_clear_token',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'clearToken'],
                        ],
                    ],
                ],
            ]);
    }

    public function sendEmailModeration($value): void
    {
        static $isSent = false;

        if ($isSent || !$value) {
            return;
        }

        $services = $this->getServiceLocator();
        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);
        if (!$user) {
            return;
        }

        if (!$user->isActive()) {
            $message = new \Omeka\Stdlib\Message(
                'You cannot send a message to confirm registration: user is not active.' // @translate
            );
            $messenger->addError($message);
            return;
        }

        $settings = $services->get('Omeka\Settings');

        $api = $services->get('Omeka\ApiManager');
        $userRepresentation = $api->read('users', ['id' => $user->getId()])->getContent();

        $guestSite = $this->guestSite($userRepresentation);
        if (!$guestSite) {
            try {
                $guestSite = $api->read('sites', ['id' => $settings->get('default_site', 1)])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $message = new \Omeka\Stdlib\Message(
                    'A default site should be set or the user should have a site in order to confirm registration.' // @translate
                );
                $messenger->addError($message);
                return;
            }
        }

        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($guestSite->id());
        $config = $this->getConfig()['settings']['guest'];
        $subject = $siteSettings->get('guest_message_confirm_registration_email_subject')
            ?: $settings->get('guest_message_confirm_registration_email_subject');
        $body = $siteSettings->get('guest_message_confirm_registration_email')
            ?: $settings->get('guest_message_confirm_registration_email');
        $subject = $subject ?: $config['guest_message_confirm_registration_email_subject'];
        $body = $body ?: $config['guest_message_confirm_registration_email'];

        // TODO Factorize creation of email.
        $data = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'site_title' => $guestSite->title(),
            'site_url' => $guestSite->siteUrl(null, true),
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
        ];
        $subject = new PsrMessage($subject, $data);
        $body = new PsrMessage($body, $data);

        $sendEmail = $services->get('ControllerPluginManager')->get('sendEmail');
        $result = $sendEmail($user->getEmail(), $subject, $body, $user->getName());
        if ($result) {
            $isSent = true;
            $message = new PsrMessage('The message of confirmation of the registration has been sent.'); // @translate
            $messenger->addSuccess($message);
        } else {
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            $messenger->addError($message);
            $logger = $services->get('Omeka\Logger');
            $logger->err('[Guest] ' . $message);
        }
    }

    public function clearToken($value): void
    {
        if (!$value) {
            return;
        }

        $services = $this->getServiceLocator();
        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);
        if (!$user) {
            return;
        }

        /** @var \Guest\Entity\GuestToken $guestToken */
        $token = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$token || $token->isConfirmed()) {
            return;
        }
        $entityManager->remove($token);
        $entityManager->flush();
    }

    public function addUserFormValue(Event $event): void
    {
        // Set the default value for a user setting.
        $user = $event->getTarget()->vars()->user;
        $form = $event->getParam('form');
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->id());
        $settings = $services->get('Omeka\Settings');
        $skip = $settings->get('guest_terms_skip');
        $guestSettings = [
            'guest_agreed_terms',
        ];
        $config = $services->get('Config')['guest']['user_settings'];
        $fieldset = $form->get('user-settings');
        foreach ($guestSettings as $name) {
            if ($name === 'guest_agreed_terms' && $skip) {
                $fieldset->get($name)->setAttribute('value', 1);
                continue;
            }
            $fieldset->get($name)->setAttribute(
                'value',
                $userSettings->get($name, $config[$name])
            );
        }
    }

    public function deleteGuestToken(Event $event): void
    {
        $request = $event->getParam('request');

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $id = $request->getId();
        $token = $entityManager->getRepository(GuestToken::class)->findOneBy(['user' => $id]);
        if (empty($token)) {
            return;
        }
        $entityManager->remove($token);
        $entityManager->flush();
    }

    /**
     * Get the site of a user (option "guest_site").
     *
     * @param UserRepresentation $user
     * @return \Omeka\Api\Representation\SiteRepresentation|null
     */
    protected function guestSite(UserRepresentation $user): ?\Omeka\Api\Representation\SiteRepresentation
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->id());
        $guestSite = $userSettings->get('guest_site') ?: null;

        if ($guestSite) {
            try {
                $guestSite = $api->read('sites', ['id' => $guestSite], [], ['initialize' => false])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $guestSite = null;
            }
        }
        return $guestSite;
    }

    /**
     * Reset all guest agreements.
     *
     * @param bool $reset
     */
    protected function resetAgreements($reset): void
    {
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $entityManager = $services->get('Omeka\EntityManager');
        $guests = $entityManager->getRepository(\Omeka\Entity\User::class)
            ->findBy(['role' => Acl::ROLE_GUEST]);
        foreach ($guests as $user) {
            $userSettings->setTargetId($user->getId());
            $userSettings->set('guest_agreed_terms', $reset);
        }
    }

    /**
     * Reset all guest agreements via sql (quicker for big base).
     *
     * @param ServiceLocatorInterface $services
     * @param bool $reset
     */
    protected function resetAgreementsBySql(ServiceLocatorInterface $services, $reset): void
    {
        $reset = $reset ? 'true' : 'false';
        $sql = <<<SQL
DELETE FROM user_setting
WHERE id="guest_agreed_terms";

INSERT INTO user_setting (id, user_id, value)
SELECT "guest_agreed_terms", user.id, "$reset"
FROM user
WHERE role="guest";
SQL;
        $connection = $services->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->executeStatement($sql);
        }
    }

    protected function deactivateGuests(): void
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $guests = $entityManager->getRepository(\Omeka\Entity\User::class)->findBy(['role' => 'guest']);
        foreach ($guests as $user) {
            $user->setIsActive(false);
            $entityManager->persist($user);
        }
        $entityManager->flush();
    }

    /**
     * Check if an old version of module GuestUser is installed.
     *
     * @throws \Omeka\Module\Exception\ModuleCannotInstallException
     * @return bool
     */
    protected function checkOldGuestUser(): bool
    {
        $services = $this->getServiceLocator();
        $hasGuestUser = false;
        $hasOldGuestUser = false;

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        /** @var \Omeka\Entity\Module $module */
        $module = $moduleManager->getModule('GuestUser');
        $hasGuestUser = (bool) $module;
        if (!$hasGuestUser) {
            return false;
        }

        $translator = $services->get('MvcTranslator');
        $hasOldGuestUser = version_compare($module->getIni('version') ?? '', '3.3.5', '<');
        if ($hasOldGuestUser) {
            if ($module->getState() === \Omeka\Module\Manager::STATE_ACTIVE) {
                $message = $translator
                    ->translate('This module cannot be used at the same time as module GuestUser for versions lower than 3.3.5. So it should be upgraded first, or disabled (directly or in table "module" of the database). When ready, the users and settings will be upgraded for all versions.'); // @translate
                throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
            }
        }

        if (in_array($module->getState(), [
            \Omeka\Module\Manager::STATE_INVALID_INI,
            \Omeka\Module\Manager::STATE_INVALID_MODULE,
            \Omeka\Module\Manager::STATE_INVALID_OMEKA_VERSION,
            \Omeka\Module\Manager::STATE_NOT_FOUND,
            \Omeka\Module\Manager::STATE_NOT_INSTALLED,
        ])) {
            return false;
        }

        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        $message = new \Omeka\Stdlib\Message(
            'The module GuestUser is installed. Users and settings from this module are upgraded.' // @translate
        );
        $messenger->addSuccess($message);

        $message = new \Omeka\Stdlib\Message(
            'To upgrade customized templates from module GuestUser, see %sreadme%s.', // @translate
            '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-Guest">',
            '</a>'
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);

        return true;
    }
}
