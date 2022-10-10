<?php declare(strict_types=1);

namespace Guest\Controller\Site;

use Doctrine\ORM\EntityManager;
use Guest\Stdlib\PsrMessage;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Entity\User;
use Omeka\Form\UserForm;

/**
 * Manage guests pages.
 */
abstract class AbstractGuestController extends AbstractActionController
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
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManager $entityManager
     * @param array $config
     */
    public function __construct(
        AuthenticationService $authenticationService,
        EntityManager $entityManager,
        array $config
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * Redirect to admin or site according to the role of the user and setting.
     *
     * @return \Laminas\Http\Response
     */
    protected function redirectToAdminOrSite()
    {
        // Bypass settings.
        $redirectUrl = $this->params()->fromQuery('redirect');
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
     * @todo The option to skip config is probably useless.
     *
     * @param string $key
     * @param bool $skipConfig Return the setting even if empty. Allow to get an
     * empty message in some cases.
     * @return string|mixed
     */
    protected function getOption($key, $skipConfigIfEmpty = false)
    {
        $value = $this->siteSettings()->get($key) ?: $this->settings()->get($key);
        return $value || $skipConfigIfEmpty ? $value : $this->getConfig()['guest']['settings'][$key];
    }

    /**
     * Prepare the user form for public view.
     *
     * @param User $user
     * @param array $options
     * @return UserForm
     */
    protected function getUserForm(User $user = null, array $options = [])
    {
        $defaultOptions = [
            'is_public' => true,
            'user_id' => $user ? $user->getId() : 0,
            'include_password' => true,
            'include_role' => false,
            'include_key' => false,
            'include_site_role_remove' => false,
            'include_site_role_add' => false,
        ];
        $options += $defaultOptions;

        $form = $this->getForm(UserForm::class, $options);

        // Remove elements from the admin user form, that shouldn’t be available
        // in public guest form.
        $elements = [
            'default_resource_template' => 'user-settings',
        ];
        foreach ($elements as $element => $fieldset) {
            if ($fieldset) {
                $fieldset = $form->get($fieldset);
                $fieldset ? $fieldset->remove($element) : null;
            } else {
                $form->remove($element);
            }
        }
        return $form;
    }

    /**
     * Prepare the template.
     *
     * @param string $template In case of a token message, this is the action.
     * @param array $data
     * @return array Filled subject and body as PsrMessage, from templates
     * formatted with moustache style.
     */
    protected function prepareMessage($template, array $data)
    {
        $settings = $this->settings();
        $currentSite = $this->currentSite();
        $default = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'site_title' => $currentSite->title(),
            'site_url' => $currentSite->siteUrl(null, true),
            'user_email' => '',
            'user_name' => '',
            'token' => null,
        ];

        $data += $default;

        if (isset($data['token'])) {
            $data['token'] = $data['token']->getToken();
            $urlOptions = ['force_canonical' => true];
            $urlOptions['query']['token'] = $data['token'];
            $data['token_url'] = $this->url()->fromRoute(
                'site/guest/anonymous',
                ['site-slug' => $currentSite->slug(), 'action' => $template === 'update-email' ? 'validate-email' : 'confirm-email'],
                $urlOptions
            );
        }

        $mapTemplateOptions = [
            'confirm-email' => [
                'subject' => 'guest_message_confirm_email_subject',
                'body' => 'guest_message_confirm_email',
            ],
            'update-email' => [
                'subject' => 'guest_message_update_email_subject',
                'body' => 'guest_message_update_email',
            ],
        ];
        if (isset($mapTemplateOptions[$template])) {
            $subject = $this->getOption($mapTemplateOptions[$template]['subject']);
            $body = $this->getOption($mapTemplateOptions[$template]['body']);
        }
        // Allows to manage derivative modules.
        else {
            $subject = !empty($data['subject']) ? $data['subject'] : '[No subject]'; // @translate
            $body = !empty($data['body']) ? $data['body'] : '[No message]'; // @translate
        }
        unset($data['subject']);
        unset($data['body']);

        return [
            'subject' => new PsrMessage($subject, $data),
            'body' => new PsrMessage($body, $data),
        ];
    }

    /**
     * @return \Laminas\Authentication\AuthenticationService
     */
    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return array
     */
    protected function getConfig()
    {
        return $this->config;
    }
}
