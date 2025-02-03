<?php declare(strict_types=1);
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2024
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

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Guest\Entity\GuestToken;
use Guest\Permissions\Acl;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Element;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Permissions\Assertion\IsSelfAssertion;

/**
 * Guest.
 *
 * @copyright BibLibre, 2016
 * @copyright Daniel Berthereau, 2017-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Common',
    ];

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

    public function install(ServiceLocatorInterface $services): void
    {
        // Required during install because the role is set in config.
        require_once __DIR__ . '/src/Permissions/Acl.php';

        $this->installAuto($services);
    }

    /**
     * @depracated See Common 3.4.66.
     */
    protected function installAuto(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);

        $this->initTranslations();

        /**@var \Laminas\Mvc\I18n\Translator $translator */
        $translator = $services->get('MvcTranslator');

        $this->preInstall();
        if (!$this->checkDependencies()) {
            if (count($this->dependencies) === 1) {
                $message = new PsrMessage(
                    'This module requires the module "{module}".', // @translate
                    ['module' => reset($this->dependencies)]
                );
            } else {
                $message = new PsrMessage(
                    'This module requires modules "{modules}".', // @translate
                    ['modules' => implode('", "', $this->dependencies)]
                );
            }
            throw new ModuleCannotInstallException((string) $message->setTranslator($translator));
        }

        if (!$this->checkAllResourcesToInstall()) {
            $message = new PsrMessage(
                'This module has resources that cannot be installed.' // @translate
            );
            throw new ModuleCannotInstallException((string) $message->setTranslator($translator));
        }

        $sqlFile = $this->modulePath() . '/data/install/schema.sql';
        if (!$this->checkNewTablesFromFile($sqlFile)) {
            $message = new PsrMessage(
                'This module cannot install its tables, because they exist already. Try to remove them first.' // @translate
            );
            throw new ModuleCannotInstallException((string) $message->setTranslator($translator));
        }

        $this->execSqlFromFile($sqlFile);

        $this
            ->installAllResources()
            ->manageConfig('install')
            ->manageMainSettings('install')
            ->manageSiteSettings('install')
            ->manageUserSettings('install')
            ->postInstall();
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.66')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.66'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function preUninstall(): void
    {
        $this->deactivateGuests();
    }

    protected function preUpgrade(): void
    {
        // Required during upgrade because the role is set in config.
        require_once __DIR__ . '/src/Permissions/Acl.php';
    }

    /**
     * Add ACL role and rules for this module.
     */
    protected function addAclRoleAndRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // TODO To be removed when roles will be integrated in core.
        /** @see https://github.com/omeka/omeka-s/pull/2241 */

        // This check allows to add the role "guest" by dependencies without
        // complex process. It avoids issues when the module is disabled too.
        // TODO Find a way to set the role "guest" during init or via Omeka\Service\AclFactory (allowing multiple delegators).
        if (!$acl->hasRole(Acl::ROLE_GUEST)) {
            $acl->addRole(Acl::ROLE_GUEST);
        }
        if (!$acl->hasRole('guest_private')) {
            $acl->addRole('guest_private');
        }
        $acl->addRoleLabel(Acl::ROLE_GUEST, 'Guest'); // @translate

        $settings = $services->get('Omeka\Settings');
        $isOpenRegister = $settings->get('guest_open', 'moderate');

        // Rules for anonymous.
        $acl
            ->allow(
                null,
                [\Guest\Controller\Site\AnonymousController::class]
            )
            ->allow(
                null,
                [\Guest\Controller\Site\GuestController::class],
                // Redirected to login in controller.
                ['me']
            )
        ;
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

        // Rules for guest.
        $roles = $acl->getRoles();
        $acl
            ->allow(
                $roles,
                [\Guest\Controller\Site\GuestController::class]
            )
            ->allow(
                [Acl::ROLE_GUEST, 'guest_private'],
                [\Omeka\Entity\User::class],
                ['read', 'update', 'change-password'],
                new IsSelfAssertion
            )
            ->allow(
                [Acl::ROLE_GUEST, 'guest_private'],
                [\Omeka\Api\Adapter\UserAdapter::class],
                ['read', 'update']
            )
            ->deny(
                [Acl::ROLE_GUEST, 'guest_private'],
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
            )
        ;

        // Rules for api.
        if ($isOpenRegister !== 'closed') {
            $acl
                ->allow(
                    null,
                    [\Guest\Controller\ApiController::class],
                    ['login', 'session-token', 'logout', 'register']
                )
                ->allow(
                    null,
                    [\Omeka\Entity\User::class],
                    // Change role and Activate user should be set to allow external
                    // logging (ldap, saml, etc.), not only guest registration here.
                    ['create', 'change-role', 'activate-user']
                )
                ->allow(
                    null,
                    [\Omeka\Api\Adapter\UserAdapter::class],
                    'create'
                );
        }

        // This is an api, so all rest api actions are allowed when available.
        // Rights are managed via credentials.
        $acl
            ->allow(
                null,
                [
                    \Guest\Controller\ApiController::class,
                    \Guest\Controller\GuestApiController::class,
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

        // Add links to login form.
        $sharedEventManager->attach(
            '*',
            'view.login.after',
            [$this, 'addLoginLinks']
        );

        // Manage redirect to admin or site after login.
        $sharedEventManager->attach(
            '*',
            'user.login',
            [$this, 'handleUserLogin']
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

        // Add a job for module Easy Admin.
        $sharedEventManager->attach(
            \EasyAdmin\Form\CheckAndFixForm::class,
            'form.add_elements',
            [$this, 'handleEasyAdminJobsForm']
        );
        $sharedEventManager->attach(
            \EasyAdmin\Controller\Admin\CheckAndFixController::class,
            'easyadmin.job',
            [$this, 'handleEasyAdminJobs']
        );
    }

    protected function isSettingTranslatable(string $settingsType, string $name): bool
    {
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

        if ($settingsType !== 'settings'
            && $settingsType !== 'site_settings'
        ) {
            return false;
        }

        return in_array($name, $translatables);
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

    public function addLoginLinks(Event $event): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $loginView = $settings->get('guest_append_links_to_login_view');
        if (!$loginView) {
            return;
        }

        $view = $event->getTarget();
        $plugins = $view->getHelperPluginManager();

        $links = [];

        if ($plugins->has('casLoginUrl')) {
            $translate = $plugins->get('translate');
            $casLoginUrl = $plugins->get('casLoginUrl');
            $links[] = [
                'url' => $casLoginUrl(),
                'label' => $translate('CAS Login'), // @translate
                'class' => 'login-cas',
            ];
        }

        if ($plugins->has('ssoLoginLinks')) {
            $url = $plugins->get('url');
            $idps = $settings->get('singlesignon_idps') ?: [];
            // Manage old an new version of module Single Sign-On.
            foreach ($idps as $idpSlug => $idp) {
                $idpName = $idp['entity_short_id'] ?? $idp['idp_entity_short_id'] ?? $idpSlug;
                $links[] = [
                    'url' => $url('sso', ['action' => 'login', 'idp' => $idpName], true),
                    'label' => !empty($idp['entity_name'])
                        ? $idp['entity_name']
                        : (!empty($idp['idp_entity_name'])
                            ? $idp['idp_entity_name']
                            : ($idp['entity_id'] ?? $idp['idp_entity_id'] ?? $view->translate('[Unknown idp]'))), // @translate
                    'class' => str_replace(['.', ':'], '-', $idpName),
                ];
            }
        }

        // TODO Ldap is integrated inside default form.

        if ($links) {
            echo $view->partial('common/guest-login-links', [
                'links' => $links,
                'selector' => $loginView,
            ]);
        }
    }

    /**
     * @see https://github.com/omeka/omeka-s/pull/1961
     * @uses \Guest\Mvc\Controller\Plugin\UserRedirectUrl
     *
     * Copy :
     * @see \Guest\Module::handleUserLogin()
     * @see \GuestPrivate\Module::handleUserLogin()
     */
    public function handleUserLogin(Event $event): void
    {
        $userRedirectUrl = $this->getServiceLocator()->get('ControllerPluginManager')->get('userRedirectUrl');
        $userRedirectUrl();
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
                'type' => \Common\Form\Element\OptionalSiteSelect::class,
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

    public function handleEasyAdminJobsForm(Event $event): void
    {
        /**
         * @var \EasyAdmin\Form\CheckAndFixForm $form
         * @var \Laminas\Form\Element\Radio $process
         */
        $form = $event->getTarget();
        $fieldset = $form->get('module_tasks');
        $process = $fieldset->get('process');
        $valueOptions = $process->getValueOptions();
        $valueOptions['guest_reset_agreement_terms'] = 'Guest: Reset terms agreement for all guests'; // @translate
        $process->setValueOptions($valueOptions);
        $fieldset
            ->add([
                'type' => \Laminas\Form\Fieldset::class,
                'name' => 'guest_reset_agreement_terms',
                'options' => [
                    'label' => 'Options to reset agreement terms', // @translate
                ],
                'attributes' => [
                    'class' => 'guest_reset_agreement_terms',
                ],
            ]);
        $fieldset->get('guest_reset_agreement_terms')
            ->add([
                'name' => 'agreement',
                'type' => \Common\Form\Element\OptionalRadio::class,
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
                    'id' => 'guest_reset_agreement_terms-agreement',
                    'value' => 'keep',
                    'required' => false,
                ],
            ]);
    }

    public function handleEasyAdminJobs(Event $event): void
    {
        $process = $event->getParam('process');
        if ($process === 'guest_reset_agreement_terms') {
            $params = $event->getParam('params');
            $event->setParam('job', \Guest\Job\GuestAgreement::class);
            $event->setParam('args', $params['module_tasks']['guest_reset_agreement_terms'] ?? []);
        }
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
}
