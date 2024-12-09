<?php declare(strict_types=1);

namespace Guest\Controller\SiteAdmin;

use Omeka\Site\Navigation\Link\Manager as LinkManager;
use Omeka\Site\Navigation\Translator;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\SiteRepresentation;

class IndexController extends AbstractActionController
{
    /**
     * @var \Omeka\Site\Navigation\Link\Manager
     */
    protected $linkManager;

    /**
     * @var \Omeka\Site\Navigation\Translator
     */
    protected $navTranslator;

    public function __construct(LinkManager $linkManager, Translator $navTranslator)
    {
        $this->linkManager = $linkManager;
        $this->navTranslator = $navTranslator;
    }

    /**
     * Adapted from:
     * @see \Omeka\Controller\SiteAdmin\IndexController::navigationAction()
     */
    public function guestNavigationAction()
    {
        $site = $this->currentSite();
        $siteSettings = $this->siteSettings();

        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $jstree = json_decode($formData['jstree'], true);
            $menuTree = $this->navTranslator->fromJstree($jstree);
            $formData['o:navigation'] = $menuTree;
            $form->setData($formData);
            if ($form->isValid()) {
                $siteSettings->set('guest_navigation', $menuTree);
                $this->messenger()->addSuccess('Navigation successfully updated'); // @translate
                return $this->redirect()->refresh();
            }
            $this->messenger()->addFormErrors($form);
        }

        $menuTree = $siteSettings->get('guest_navigation') ?: [];

        return new ViewModel([
            'site' => $site,
            'form' => $form,
            'navTree' =>  $this->toJstree($site, $menuTree),
        ]);
    }

    /**
     * Adapted from:
     * @see \Omeka\Site\Navigation\Translator::toJstree()
     */
    protected function toJstree(SiteRepresentation $site, array $menu)
    {
        $buildLinks = null;
        $buildLinks = function ($linksIn) use (&$buildLinks, $site) {
            $linksOut = [];
            foreach ($linksIn as $data) {
                $linkType = $this->linkManager->get($data['type']);
                $linkData = $data['data'];
                $linksOut[] = [
                    'text' => $this->navTranslator->getLinkLabel($linkType, $data['data'], $site),
                    'data' => [
                        'type' => $data['type'],
                        'data' => $linkType->toJstree($linkData, $site),
                        'url' => $this->navTranslator->getLinkUrl($linkType, $data, $site),
                    ],
                    'children' => $data['links'] ? $buildLinks($data['links']) : [],
                ];
            }
            return $linksOut;
        };
        $links = $buildLinks($menu);
        return $links;
    }
}
