<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

use Guest\Permissions\Acl as GuestAcl;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;
use Omeka\Form\UserForm;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Permissions\Acl;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;

class Register extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/guest-register';

    /**
     * @var \Omeka\Permissions\Acl $acl
     */
    protected $acl;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Messenger
     */
    protected $messenger;

    /**
     * @var bool
     */
    protected $hasModuleUserNames;

    public function __construct(
        Acl $acl,
        FormElementManager $formElementManager,
        Messenger $messenger,
        bool $hasModuleUserNames
    ) {
        $this->acl = $acl;
        $this->formElementManager = $formElementManager;
        $this->messenger = $messenger;
        $this->hasModuleUserNames = $hasModuleUserNames;
    }

    public function getLabel()
    {
        return 'Guest: Register'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        return '<p>'
            . $view->translate('Display the register form. The options are set in site settings.') // @translate
            . '</p>';
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        if ($view->setting('guest_open', 'moderate') === 'closed') {
            return '';
        }

        $urlRegister = $view->url('site/guest/anonymous', [
            'site-slug' => $block->page()->site()->slug(),
            'controller' => \Guest\Controller\Site\AnonymousController::class,
            'action' => 'register',
        ]);

        $user = $view->identity();
        if ($user) {
            header('Location: ' . $urlRegister, true, 302);
            die();
        }

        /** @var \Omeka\View\Helper\Params $params */
        $params = $view->params();
        $post = $params->fromPost();

        // TODO Clarifiy process when blocks login and register are on the same page.
        // Manage the case where blocks login and register are on the same page.
        if ($post
            && empty($post['loginform_csrf'])
            && empty($post['submit_token'])
        ) {
            // For now, post is not possible: it is redirected to standard register page.
            header('Location: ' . $urlRegister, true, 302);
            die();
        }

        // Needed to prepare the form.
        $roleDefault = $this->getDefaultRole();
        $user = new User();
        $user->setRole($roleDefault);

        $form = $this->getUserForm($user);
        $form->setAttribute('action', $urlRegister);

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'form' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
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

        /** @var \Guest\Form\UserForm $form */
        /** @var \Omeka\Form\UserForm $form */
        $form = $this->formElementManager->get(UserForm::class, $options);

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
        foreach ($elements as $element => $fieldset) {
            $fieldset && $form->has($fieldset)
                ? $form->get($fieldset)->remove($element)
                : $form->remove($element);
        }
        return $form;
    }
}
