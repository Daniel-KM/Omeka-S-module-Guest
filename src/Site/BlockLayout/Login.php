<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

use Guest\Mvc\Controller\Plugin\ValidateLogin;
use Laminas\Form\FormElementManager;
use Laminas\Http\Request;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;
use TwoFactorAuth\Form\TokenForm;
use TwoFactorAuth\Mvc\Controller\Plugin\TwoFactorLogin;

class Login extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    use TraitGuest;

    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/guest-login';

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Messenger
     */
    protected $messenger;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var TwoFactorLogin
     */
    protected $twoFactorLogin;

    /**
     * @var ValidateLogin
     */
    protected $validateLogin;

    /**
     * @var bool
     */
    protected $hasModuleUserNames;

    public function __construct(
        FormElementManager $formElementManager,
        Messenger $messenger,
        Request $request,
        TwoFactorLogin $twoFactorLogin,
        ValidateLogin $validateLogin,
        bool $hasModuleUserNames
    ) {
        $this->formElementManager = $formElementManager;
        $this->messenger = $messenger;
        $this->request = $request;
        $this->twoFactorLogin = $twoFactorLogin;
        $this->validateLogin = $validateLogin;
        $this->hasModuleUserNames = $hasModuleUserNames;
    }

    public function getLabel()
    {
        return 'Guest: Login'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        $dataClean = [];
        $dataClean['display_form'] = ($data['display_form'] ?? 'login') === 'register' ? 'register' : 'login';

        $block->setData($dataClean);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        return '<p>'
            . $view->translate('Display the login form.') // @translate
            . '</p>';
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        // Redirect to admin or guest account when user is authenticated.
        $user = $view->identity();
        if ($user) {
            $this->redirectToAdminOrSite($view);
            return '';
        }

        // The process is slightly different from module TwoFactorAuth, because
        // there is no route for login-token.

        // Further, the ajax for 2fa-login is managed by module TwoFactorAuth.

        /** @var \Omeka\View\Helper\Params $params */
        $params = $view->params();
        $post = $params->fromPost();
        if (!empty($post['token_email']) || !empty($post['submit_token'])) {
            return $this->loginToken($view, $block, $templateViewScript);
        }

        if (!$post && $params->fromQuery('resend_token')) {
            return $this->resendToken($view, $block, $templateViewScript);
        }

        $loginWithoutForm = $view->siteSetting('guest_login_without_form');

        $form = $loginWithoutForm
            ? null
            : $this->formElementManager->get($this->hasModuleUserNames ? \UserNames\Form\LoginForm::class : \Omeka\Form\LoginForm::class);

        if ($post && $form) {
            $result = $this->validateLogin->__invoke($form);
            if ($result === null) {
                // Internal error (no mail sent).
                return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], true);
            } elseif ($result === false ) {
                // Email or password error, so retry below.
                // Slow down the process to avoid brute force.
                sleep(3);
            } elseif ($result === 0) {
                // Email or password error in 2FA, so retry below.
                // Sleep is already processed.
            } elseif ($result === 1) {
                // Success login in first step in 2FA, so go to second step.
                $formToken = $this->formElementManager->get(TokenForm::class);
                $templateViewScript = 'common/block-template/guest-login-token';
            } elseif (is_string($result)) {
                // Moderation or confirmation missing or other issue.
                $this->messenger->addError($result);
            } else {
                // Here, the user is authenticated.
                $this->redirectToAdminOrSite($view);
                return '';
            }
        } elseif ($post) {
            // Manage login without form (cas, ldap, sso).
            $this->redirectToAdminOrSite($view);
        }

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
        ];

        if ($view->setting('twofactorauth_use_dialog')
            && class_exists('TwoFactorAuth\Module', false)
        ) {
            // For ajax, use standard action.
            $form->setAttribute('action', $view->url('login'));
            $formToken = $this->formElementManager->get(TokenForm::class)->setAttribute('action', $view->url('login'));
            $vars['form'] = $form;
            $vars['formToken'] = $formToken;
        } else {
            isset($formToken)
                ? $vars['formToken'] = $formToken
                : $vars['form'] = $form;
        }

        return $view->partial($templateViewScript, $vars);
    }

    /**
     * @see \Guest\Controller\Site\AnonymousController::loginToken()
     * @see \Guest\Site\BlockLayout\Login::loginToken()
     * @see \TwoFactorAuth\Controller\LoginController::loginTokenAction()
     */
    protected function loginToken(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        // Check if the first step was just processed.
        $isFirst = (bool) $this->request->getMetadata('first');

        // Ajax is managed by module TwoFactorAuth.

        if (!$isFirst && $this->request->isPost()) {
            $data = $this->request->getPost();
            $form = $this->formElementManager->get(TokenForm::class);
            $form->setData($data);
            if ($form->isValid()) {
                /**
                 * @var \Laminas\Http\PhpEnvironment\Request $request
                 * @var \TwoFactorAuth\Mvc\Controller\Plugin\TwoFactorLogin $twoFactorLogin
                 */
                $validatedData = $form->getData();
                $result = $this->twoFactorLogin->validateLoginStep2($validatedData['token_email']);
                if ($result === null) {
                    // Internal error (no mail sent).
                    header('Location: ' . $block->page()->siteUrl(null, true), true, 302);
                    die();
                } elseif ($result) {
                    return $this->redirectToAdminOrSite($view);
                }
            } else {
                $this->messenger->addFormErrors($form);
            }
        }

        $form = $this->formElementManager->get(TokenForm::class);
        $templateViewScript = 'common/block-template/guest-login-token';

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'formToken' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
    }

    /**
     * Adapted:
     * @see \Guest\Controller\Site\AnonymousController::resendToken();
     * @see \Guest\Site\BlockLayout\Login::resendToken()
     * @see \TwoFactorAuth\Controller\LoginController::resendTokenAction();
     */
    protected function resendToken(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $codeKey = $this->request->getQuery('resend_token');
        if ($codeKey) {
            $result = $this->twoFactorLogin->resendToken();
        } else {
            $result = false;
        }

        // Ajax is managed by module TwoFactorAuth.

        $result
            ? $this->messenger->addSuccess('A new code was resent.') // @translate
            : $this->messenger->addError('Unable to send email.'); // @translate

        $form = $this->formElementManager->get(TokenForm::class);
        $templateViewScript = 'common/block-template/guest-login-token';

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'formToken' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
    }
}
