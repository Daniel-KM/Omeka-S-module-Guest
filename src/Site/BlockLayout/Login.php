<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

use Guest\Mvc\Controller\Plugin\ValidateLogin;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;

class Login extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
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
        ValidateLogin $validateLogin,
        bool $hasModuleUserNames
    ) {
        $this->formElementManager = $formElementManager;
        $this->messenger = $messenger;
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

        /** @var \Omeka\View\Helper\Params $params */
        $params = $view->params();
        $post = $params->fromPost();

        $form = $this->formElementManager->get(
            $this->hasModuleUserNames
                ? \UserNames\Form\LoginForm::class
                : \Omeka\Form\LoginForm::class
        );
        if ($post) {
            $result = $this->validateLogin->__invoke($form);
            if ($result === true) {
                $this->redirectToAdminOrSite($view);
                return '';
            } elseif (is_string($result)) {
                $this->messenger->addError($result);
            }
        }

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'form' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
    }

    /**
     * Redirect to admin or site according to the role of the user and setting.
     *
     * @return \Laminas\Http\Response
     *
     * Adapted:
     * @see \Guest\Controller\Site\AbstractGuestController::redirectToAdminOrSite()
     * @see \Guest\Site\BlockLayout\Login::redirectToAdminOrSite()
     */
    protected function redirectToAdminOrSite(PhpRenderer $view): void
    {
        // Bypass settings if set in url query.
        $redirectUrl = $view->params()->fromQuery('redirect');

        if (!$redirectUrl) {
            $redirect = $view->siteSetting('guest_redirect') ?: $view->setting('guest_redirect');
            switch ($redirect) {
                case empty($redirect):
                case 'home':
                    if ($view->userIsAllowed('Omeka\Controller\Admin\Index')) {
                        $redirectUrl = $view->url('admin', [], true);
                        break;
                    }
                    // no break.
                case 'site':
                    $redirectUrl = $view->url('site', [], true);
                    break;
                case 'me':
                    $redirectUrl = $view->url('site/guest', ['action' => 'me'], [], true);
                    break;
                default:
                    $redirectUrl = $redirect;
                    break;
            }
        }

        header('Location: ' . $redirectUrl, true, 302);
        die();
    }
}
