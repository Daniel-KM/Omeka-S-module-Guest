<?php declare(strict_types=1);

namespace Guest\Controller;

use Common\Stdlib\PsrMessage;
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
        if ($redirectUrl) {
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
     */
    protected function getOption($key)
    {
        return $this->siteSettings()->get($key)
            ?: $this->settings()->get($key)
            ?: ($this->getConfig()['guest']['settings'][$key] ?? null);
    }

    /**
     * Prepare the user form for public view.
     *
     * Adapted:
     * @see \Guest\Controller\Site\AbstractGuestController::getUserForm()
     * @see \Guest\Site\BlockLayout\Register::getUserForm()
     */
    protected function getUserForm(?User $user = null): UserForm
    {
        $hasUser = $user && $user->getId();

        $options = [
            'is_public' => true,
            'user_id' => $user ? $user->getId() : 0,
            'include_role' => false,
            'include_admin_roles' => false,
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
            'site_url' => $site ? $site->siteUrl(null, true): null,
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
                $body = $getValue('guest_message_confirm_email_subject');
                break;

            // Allows to manage derivative modules.
            default:
                $subject = !empty($data['subject']) ? $data['subject'] : '[No subject]'; // @translate
                $body = !empty($data['body']) ? $data['body'] : '[No message]'; // @translate
                break;
        }

        // The url may be protected by html-purifier.
        $subject = str_replace('%7Btoken_url%7D', '{token_url}', $subject);
        $body = str_replace('%7Btoken_url%7D', '{token_url}', $body);

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
}
