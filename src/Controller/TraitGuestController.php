<?php declare(strict_types=1);

namespace Guest\Controller;

use Common\Stdlib\PsrMessage;
use Doctrine\Common\Collections\Criteria;
use Guest\Permissions\Acl as GuestAcl;
use Laminas\Session\Container as SessionContainer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;
use Omeka\Form\UserForm;

trait TraitGuestController
{
    protected $defaultRoles = [
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
        \Omeka\Permissions\Acl::ROLE_AUTHOR,
        \Omeka\Permissions\Acl::ROLE_RESEARCHER,
    ];

    /**
     * Redirect to admin or site according to the role of the user and setting.
     *
     * @return \Laminas\Http\Response
     *
     * Adapted:
     * @see \Contribute\Controller\Site\ContributionController::redirectAfterSubmit()
     * @see \Guest\Controller\Site\AbstractGuestController::redirectToAdminOrSite()
     * @see \Guest\Site\BlockLayout\TraitGuest::redirectToAdminOrSite()
     * @see \SingleSignOn\Controller\SsoController::redirectToAdminOrSite()
     */
    protected function redirectToAdminOrSite()
    {
        // Bypass settings if set in url query.
        $redirectUrl = $this->params()->fromQuery('redirect_url')
            ?: $this->params()->fromQuery('redirect')
            ?: SessionContainer::getDefaultManager()->getStorage()->offsetGet('redirect_url');
        if ($redirectUrl && $this->isLocalUrl($redirectUrl)) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        $redirect = $this->getOption('guest_redirect');
        switch ($redirect) {
            case empty($redirect):
            case 'home':
                $user = $this->getAuthenticationService()->getIdentity();
                if (in_array($user->getRole(), $this->defaultRoles)) {
                    return $this->redirect()->toRoute('admin', [], true);
                }
                // no break.
            case 'site':
                return $this->redirect()->toRoute('site', [], true);
            case 'me':
                return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
            default:
                return $this->redirect()->toUrl($redirect);
        }
    }

    /**
     * Get a site setting, or the main setting if empty, or the default config.
     *
     * It is mainly used to get messages.
     *
     * @param string $key
     * @return string|mixed
     *
     * @todo Replace with fallbackSetting().
     */
    protected function getOption($key)
    {
        $value = $this->siteSettings()->get($key)
            ?: $this->settings()->get($key)
            ?: ($this->getConfig()['guest']['settings'][$key] ?? null);
        return is_string($value)
            ? strtr($value, ['%7B' => '{', '%7D' => '}'])
            : $value;
    }

    /**
     * @todo Factorize.
     * @see \Guest\Controller\TraitGuestController::getDefaultRole()
     * @see \Guest\Site\BlockLayout\Register::getDefaultRole()
     */
    protected function getDefaultRole(): string
    {
        $settings = $this->settings();
        $registerRoleDefault = $settings->get('guest_register_role_default') ?: GuestAcl::ROLE_GUEST;
        if (!in_array($registerRoleDefault, $this->acl->getRoles(), true)) {
            $this->logger()->warn(
                'The role {role} is not valid. Role "guest" is used instead.', // @translate
                ['role' => $registerRoleDefault]
            );
            $registerRoleDefault = GuestAcl::ROLE_GUEST;
        } elseif ($this->acl->isAdminRole($registerRoleDefault)) {
            $this->logger()->warn(
                'The role {role} is an admin role and cannot be used for registering. Role "guest" is used instead.', // @translate
                ['role' => $registerRoleDefault]
            );
            $registerRoleDefault = GuestAcl::ROLE_GUEST;
        }
        return $registerRoleDefault;
    }

    protected function isAllowedRole(?string $role, ?string $page): bool
    {
        $settings = $this->settings();
        return $role
            && $page
            && in_array($page, $settings->get('guest_allowed_roles_pages', []), true)
            && in_array($role, $settings->get('guest_allowed_roles', []), true)
            && !$this->acl->isAdminRole($role)
        ;
    }

    /**
     * Prepare the user form for public view.
     *
     * Adapted:
     * @see \Guest\Controller\Site\AbstractGuestController::getUserForm()
     * @see \Guest\Site\BlockLayout\Register::getUserForm()
     */
    protected function getUserForm(?User $user = null, ?string $page = null): UserForm
    {
        $hasUser = $user && $user->getId();

        $includeRole = false;
        $allowedRoles = [];
        if ($page) {
            $settings = $this->settings();
            $allowedRoles = $settings->get('guest_allowed_roles', []);
            $allowedPages = $settings->get('guest_allowed_roles_pages', []);
            if (count($allowedRoles) > 1 && in_array($page, $allowedPages)) {
                $includeRole = true;
            } else {
                $allowedRoles = [];
            }
        }

        $options = [
            'is_public' => true,
            'user_id' => $user ? $user->getId() : 0,
            'include_role' => $includeRole,
            'include_admin_roles' => false,
            'allowed_roles' => $allowedRoles,
            'include_is_active' => false,
            'current_password' => $hasUser,
            'include_password' => true,
            'include_key' => false,
            'include_site_role_remove' => false,
            'include_site_role_add' => false,
        ];

        // If the user is authenticated by Cas, Shibboleth, Ldap or Saml, email
        // and password should be removed.
        $isExternalUser = $this->isExternalUser($user);
        if ($isExternalUser) {
            $options['current_password'] = false;
            $options['include_password'] = false;
        }

        /** @var \Guest\Form\UserForm $form */
        /** @var \Omeka\Form\UserForm $form */
        $form = $this->getForm(UserForm::class, $options);

        // Remove elements from the admin user form, that shouldn’t be available
        // in public guest form.
        // Most of admin elements are now removed directly since the form is
        // overridden. Nevertheless, some modules add elements.
        // For user profile: append options "exclude_public_show" and "exclude_public_edit"
        // to elements.
        $elements = [
            'filesideload_user_dir' => 'user-settings',
            'locale' => 'user-settings',
        ];
        if ($isExternalUser) {
            $elements['o:email'] = 'user-information';
            $elements['o:name'] = 'user-information';
            $elements['o:role'] = 'user-information';
            $elements['o:is_active'] = 'user-information';
        }
        foreach ($elements as $element => $fieldset) {
            $fieldset && $form->has($fieldset)
                ? $form->get($fieldset)->remove($element)
                : $form->remove($element);
        }

        if ($form->has('change-password') && $form->get('change-password')->has('password-confirm')) {
            $form->get('change-password')->get('password-confirm')->setLabels(
                'Password', // @translate
                'Confirm password' // @translate
            );
        }

        $form->getAttribute('id') ?: $form->setAttribute('id', 'user-form');

        return $form;
    }

    /**
     * Check if a user is authenticated via a third party (cas, ldap, saml, shibboleth).
     *
     * @todo Integrate Ldap and Saml. Empty password is not sure.
     */
    protected function isExternalUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($this->getPluginManager()->has('isCasUser')) {
            $result = $this->getPluginManager()->get('isCasUser')($user);
            if ($result) {
                return true;
            }
        }
        return false;
    }

    protected function hasModuleUserNames(): bool
    {
        static $hasModule = null;
        if (is_null($hasModule)) {
            // A quick way to check the module without services.
            try {
                $this->api()->search('usernames', ['limit' => 0])->getTotalResults();
                $hasModule = true;
            } catch (\Exception $e) {
                $hasModule = false;
            }
        }
        return $hasModule;
    }

    /**
     * Prepare the template.
     *
     * @param string $template In case of a token message, this is the action.
     * @param array $data
     * @param SiteRepresentation $site
     * @return array Filled subject and body as PsrMessage, from templates
     * formatted with moustache style.
     */
    protected function prepareMessage($template, array $data, ?SiteRepresentation $site = null)
    {
        $settings = $this->settings();

        $site = $site ?: $this->currentSite();
        if (empty($site) && $settings->get('guest_register_site')) {
            throw new \Exception('Missing site.'); // @translate
        }

        $siteSettings = $site ? $this->siteSettings() : null;

        $default = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'site_title' => $site ? $site->title() : null,
            'site_url' => $site ? $site->siteUrl(null, true) : null,
            'user_name' => '',
            'user_email' => '',
            'token' => null,
            'token_url' => null,
        ];

        $data += $default;

        if (isset($data['token'])) {
            /** @var \Guest\Entity\GuestToken $token */
            $token = $data['token'];
            $data['token'] = $token->getToken();
            $actions = [
                'notify-registration' => 'notify-registration',
                'confirm-email' => 'confirm-email',
                'confirm-email-text' => 'confirm-email',
                'register-email-api' => 'confirm-email',
                'register-email-api-text' => 'confirm-email',
                'update-email' => 'validate-email',
            ];
            $action = $actions[$template] ?? $template;
            $urlOptions = ['force_canonical' => true];
            $urlOptions['query']['token'] = $data['token'];
            if ($site) {
                $data['token_url'] = $this->url()->fromRoute(
                    'site/guest/anonymous',
                    ['site-slug' => $site->slug(),  'action' => $action],
                    $urlOptions
                );
            } else {
                // TODO Add an url to validate email by token (an url is not possible to fix issue in phone).
                // For now, it should be disabled (set "guest_register_email_is_valid").
                // $data['token_url'] = $this->url()->fromRoute('guest-token', [], $urlOptions);
                $data['token_url'] = null;
                if (!$this->settings()->get('guest_register_email_is_valid')) {
                    $this->logger()->warn('It is currently not possible to send a token for a private site. Set option to skip email validation.'); // @translate
                }
            }
        }

        if ($siteSettings) {
            $getValue = fn ($key) => $siteSettings->get($key)
                ?: $settings->get($key)
                ?: ($this->config['guest']['site_settings'][$key] ?? $this->config['guest']['settings'][$key] ?? null);
        } else {
            $getValue = fn ($key) => $settings->get($key) ?: ($this->config['guest']['settings'][$key] ?? null);
        }

        $isText = substr($template, -5) === '-text';
        if ($isText) {
            $template = substr($template, 0, -5);
        }

        switch ($template) {
            case 'notify-registration':
                $subject = $getValue('guest_message_notify_registration_email_subject');
                $body = $getValue('guest_message_notify_registration_email');
                break;

            case 'confirm-email':
                $subject = $getValue('guest_message_confirm_email_subject');
                $body = $getValue('guest_message_confirm_email');
                break;

            case 'update-email':
                $subject = $getValue('guest_message_update_email_subject');
                $body = $getValue('guest_message_update_email');
                break;

            case 'register-email-api':
                $subject = $getValue('guest_message_confirm_registration_email_subject');
                $body = $getValue('guest_message_confirm_registration_email');
                break;

            case 'validate-email':
                $subject = $getValue('guest_message_confirm_email_subject');
                $body = $getValue('guest_message_confirm_email');
                break;

            // Allows to manage derivative modules.
            default:
                $subject = !empty($data['subject']) ? $data['subject'] : '[No subject]'; // @translate
                $body = !empty($data['body']) ? $data['body'] : '[No message]'; // @translate
                break;
        }

        // The url may be protected by html-purifier.
        $subject = strtr($subject, ['%7Btoken_url%7D' => '{token_url}']);
        $body = strtr($body, ['%7Btoken_url%7D' => '{token_url}']);

        if ($isText) {
            $subject = strip_tags($subject);
            $body = strip_tags($body);
        }

        unset($data['subject']);
        unset($data['body']);
        $subject = new PsrMessage($subject, $data);
        $body = new PsrMessage($body, $data);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    protected function isLoginWIthoutForm(): bool
    {
        $loginWithoutForm = (bool) $this->siteSettings()->get('guest_login_without_form');
        if (!$loginWithoutForm) {
            return false;
        }

        // Check if in a block "guest login" has option "show_login_form".
        $site = $this->currentSite();
        if (!$site) {
            return true;
        }
        $siteId = $site->id();

        /**
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Omeka\Entity\SitePageBlock $block
         */
        $services = $site->getServiceLocator();

        /* // TODO Use entity manager, but it is not quicker here because search is done in all the sie
        $entityManager = $services->get('Omeka\EntityManager');
        $blockRepository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
        $criteria = Criteria::create()->where(Criteria::expr()->eq('layout', 'login'));
        $blocks = $blockRepository->findBy(['site' => $siteId, $criteria]);
         */

        $connection = $services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('block.id, block.data')
            ->from('site_page_block', 'block')
            ->innerJoin('block', 'site_page', 'site_page', 'site_page.id = block.page_id')
            ->where('site_page.site_id = :site_id')
            ->andWhere('block.layout = :layout')
            ->andWhere('block.data LIKE :key')
            ->setParameter('site_id', $siteId)
            ->setParameter('layout', 'login')
            // Search in json, but json can be indented and spaced.
            // ->setParameter('key', '%"show_login_form":"yes"%')
            ->setParameter('key', '%"show_login_form"%')
        ;
        $blocks = $qb->execute()->fetchAllAssociative();

        foreach ($blocks as $block) {
            $data = json_decode($block['data'], true);
            if (isset($data['show_login_form']) && $data['show_login_form'] === 'yes') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a URL is local (relative or same host) to prevent open redirect.
     */
    protected function isLocalUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        // Allow relative URLs starting with /
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        // Check if URL is on the same host.
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            // Relative URL without leading slash - allow it.
            return true;
        }

        // Compare with current host.
        $request = $this->getRequest();
        $currentHost = $request->getUri()->getHost();

        return $parsedUrl['host'] === $currentHost;
    }

    /**
     * Validate registration data before user creation.
     *
     * @param array $data Registration data with email, username, etc.
     * @return array Array with 'valid' boolean and 'error' message if invalid.
     */
    protected function validateRegistrationData(array $data): array
    {
        if (empty($data['email'])) {
            return [
                'valid' => false,
                'error' => $this->translate('Email is required.'), // @translate
            ];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => $this->translate('Invalid email.'), // @translate
            ];
        }

        // Check for existing user with this email.
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            $guestToken = $this->entityManager->getRepository(\Guest\Entity\GuestToken::class)
                ->findOneBy(['email' => $data['email']], ['id' => 'DESC']);

            if (empty($guestToken) || $guestToken->isConfirmed()) {
                return [
                    'valid' => false,
                    'error' => $this->translate('Already registered.'), // @translate
                ];
            }

            // Check if email is always valid option changed.
            if ($guestToken && $this->settings()->get('guest_register_email_is_valid')) {
                $guestToken->setConfirmed(true);
                $this->entityManager->persist($guestToken);
                $this->entityManager->flush();
                return [
                    'valid' => false,
                    'error' => $this->translate('Already registered.'), // @translate
                ];
            }

            return [
                'valid' => false,
                'error' => $this->translate('Check your email to confirm your registration.'), // @translate
            ];
        }

        // Validate UserNames if module is active.
        if ($this->hasModuleUserNames()) {
            $userNameAdapter = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()
                ->get('Omeka\ApiAdapterManager')->get('usernames');
            $userName = new \UserNames\Entity\UserNames;
            $userName->setUserName($data['o-module-usernames:username'] ?? '');
            $errorStore = new \Omeka\Stdlib\ErrorStore;
            $userNameAdapter->validateEntity($userName, $errorStore);
            $errors = $errorStore->getErrors();
            if (!empty($errors['o-module-usernames:username'])) {
                return [
                    'valid' => false,
                    'error' => $this->translate(reset($errors['o-module-usernames:username'])),
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Create a guest user from registration data.
     *
     * @param array $userInfo User data for API create.
     * @param array $data Original registration data with password etc.
     * @return array Array with 'user' entity or 'error' message.
     */
    protected function createRegistrationUser(array $userInfo, array $data): array
    {
        try {
            /** @var \Omeka\Entity\User $user */
            $user = $this->api()->create('users', $userInfo, [], ['responseContent' => 'resource'])->getContent();
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            // This exception may be thrown by module UserNames.
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userInfo['o:email']]);

            if (!$user) {
                return [
                    'user' => null,
                    'error' => $this->translate('Unknown error before creation of user.'), // @translate
                ];
            }

            if ($this->hasModuleUserNames()) {
                $userNames = $this->api()->search('usernames', ['user' => $user->getId()])->getContent();
                if (!$userNames && !empty($userInfo['o-module-usernames:username'])) {
                    $userName = new \UserNames\Entity\UserNames;
                    $userName->setUser($user);
                    $userName->setUserName($userInfo['o-module-usernames:username']);
                    $this->entityManager->persist($userName);
                    $this->entityManager->flush();
                }
            } else {
                $this->logger()->err(
                    'An error occurred after creation of the guest user: {exception}', // @translate
                    ['exception' => $e]
                );
            }
        } catch (\Exception $e) {
            $this->logger()->err($e);
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userInfo['o:email']]);

            if (!$user) {
                return [
                    'user' => null,
                    'error' => $this->translate('Unknown error during creation of user.'), // @translate
                ];
            }
        }

        return ['user' => $user, 'error' => null];
    }

    /**
     * Add user as viewer to a site.
     *
     * @param User $user User entity.
     * @param \Omeka\Entity\Site $siteEntity Site entity.
     */
    protected function setupUserSitePermission(User $user, \Omeka\Entity\Site $siteEntity): void
    {
        $sitePermission = new \Omeka\Entity\SitePermission;
        $sitePermission->setSite($siteEntity);
        $sitePermission->setUser($user);
        $sitePermission->setRole(\Omeka\Entity\SitePermission::ROLE_VIEWER);
        $siteEntity->getSitePermissions()->add($sitePermission);
        $this->entityManager->persist($siteEntity);
        $this->entityManager->flush();
    }

    /**
     * Check if the registering is open or moderated.
     *
     * @return bool True if open, false if moderated (or closed).
     */
    protected function isOpenRegister(): bool
    {
        return $this->settings()->get('guest_open') === 'open';
    }

    /**
     * Check if a user is logged.
     *
     * @return bool
     */
    protected function isUserLogged(): bool
    {
        return $this->getAuthenticationService()->hasIdentity();
    }

    /**
     * Extract password from form values.
     *
     * @param array $values Form values with 'change-password' key.
     * @return string|null Password or null if empty/not set.
     */
    protected function extractPasswordFromFormValues(array $values): ?string
    {
        $password = $values['change-password']['password-confirm']['password'] ?? null;
        return !empty($password) ? $password : null;
    }

    /**
     * Add user to default sites configured in settings.
     *
     * @param User $user User entity.
     */
    protected function addUserToDefaultSites(User $user): void
    {
        $settings = $this->settings();
        $defaultSites = $settings->get('guest_default_sites', []);
        if (!$defaultSites) {
            return;
        }

        $entityManager = $this->entityManager;
        foreach ($defaultSites as $defaultSite) {
            $site = $entityManager->find(\Omeka\Entity\Site::class, (int) $defaultSite);
            if (!$site) {
                continue;
            }
            $sitePermission = new \Omeka\Entity\SitePermission();
            $sitePermission->setSite($site);
            $sitePermission->setUser($user);
            $sitePermission->setRole(\Omeka\Entity\SitePermission::ROLE_VIEWER);
            $entityManager->persist($sitePermission);
        }
    }

    /**
     * Send registration notification to configured administrators.
     *
     * @param User $user The newly registered user.
     * @return bool True if sent successfully or no notification configured.
     */
    protected function sendRegistrationNotification(User $user): bool
    {
        $emails = $this->getOption('guest_notify_register') ?: null;
        if (!$emails) {
            return true;
        }

        $message = new PsrMessage(
            'A new user is registering: {user_email} ({url}).', // @translate
            [
                'user_email' => $user->getEmail(),
                'url' => $this->url()->fromRoute('admin/id', ['controller' => 'user', 'id' => $user->getId()], ['force_canonical' => true]),
            ]
        );

        return $this->sendEmail($message, $this->translate('[Omeka Guest] New registration'), $emails); // @translate
    }

    /**
     * Send confirmation email to newly registered user.
     *
     * @param User $user The newly registered user.
     * @param \Guest\Entity\GuestToken|null $guestToken Token for confirmation (null if email always valid).
     * @param \Omeka\Api\Representation\SiteRepresentation|null $site Current site.
     * @return bool True if sent successfully.
     */
    protected function sendRegistrationConfirmationEmail(User $user, $guestToken, $site = null): bool
    {
        $message = $this->prepareMessage('confirm-email', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'token' => $guestToken,
            'site' => $site,
        ]);

        return $this->sendEmail($message['body'], $message['subject'], [$user->getEmail() => $user->getName()]);
    }

    /**
     * Process user settings from registration data.
     *
     * @param User $user The user entity.
     * @param array $data Registration data containing user settings.
     */
    protected function processUserSettings(User $user, array $data): void
    {
        if (empty($data['o:settings']) || !is_array($data['o:settings'])) {
            return;
        }

        $id = $user->getId();
        $userSettings = $this->userSettings();
        foreach ($data['o:settings'] as $settingId => $settingValue) {
            $userSettings->set($settingId, $settingValue, $id);
        }
    }
}
