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
        $loginWithoutForm = $view->siteSetting('guest_login_without_form');

        $form = $loginWithoutForm
            ? null
            : $this->formElementManager->get($this->hasModuleUserNames ? \UserNames\Form\LoginForm::class : \Omeka\Form\LoginForm::class);

        if ($post && $form) {
            $result = $this->validateLogin->__invoke($form);
            if ($result === true) {
                $this->redirectToAdminOrSite($view);
                return '';
            } elseif (is_string($result)) {
                $this->messenger->addError($result);
            }
        } elseif ($post) {
            // Manage login without form.
            $this->redirectToAdminOrSite($view);
        }

        $vars = [
            'site' => $block->page()->site(),
            'block' => $block,
            'form' => $form,
        ];
        return $view->partial($templateViewScript, $vars);
    }
}
