<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;

class ForgotPassword extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    use TraitGuest;

    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/guest-forgot-password';

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Messenger
     */
    protected $messenger;

    public function __construct(
        FormElementManager $formElementManager,
        Messenger $messenger
    ) {
        $this->formElementManager = $formElementManager;
        $this->messenger = $messenger;
    }

    public function getLabel()
    {
        return 'Guest: Forgot password'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        return '<p>'
            . $view->translate('Display the form to recover the password.') // @translate
            . '</p>';
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        // Redirect to admin or guest account when user is authenticated.
        $user = $view->identity();
        if ($user) {
            $redirectUrl = $view->userIsAllowed('Omeka\Controller\Admin\Index')
                ? $view->url('admin/id', ['controller' => 'user', 'action' => 'edit', 'id' => $user->getId()], ['fragment' => 'change-password'], true)
                : $view->url('site/guest/guest', ['action' => 'update-account'], [], true);
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }

        // No form means no rights to change password.
        $loginWithoutForm = $view->siteSetting('guest_login_without_form');
        if ($loginWithoutForm) {
            $this->messenger->addError('You cannot change your password here.'); // @translate
            $this->redirectToAdminOrSite($view);
            return '';
        }

        /** @var \Omeka\View\Helper\Params $params */
        $params = $view->params();
        $post = $params->fromPost();
        if ($post) {
            $redirectUrl = $view->url('site/guest/anonymous', ['action' => 'login'], [], true);
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }

        $form = $this->formElementManager->get(\Omeka\Form\ForgotPasswordForm::class);

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'form' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
    }
}
