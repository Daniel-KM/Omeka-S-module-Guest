<?php declare(strict_types=1);

namespace Guest\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\I18n\Translator as I18n;
use Laminas\View\Helper\Url;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Site\Navigation\Link\Manager as LinkManager;

/**
 * Genericized from \Omeka\Site\Navigation\Translator
 * @see \Omeka\Site\Navigation\Translator
 * @see \Guest\Mvc\Controller\Plugin\NavigationTranslator
 * @see \Menu\Mvc\Controller\Plugin\NavigationTranslator
 */
class GuestNavigationTranslator extends AbstractPlugin
{
    /**
     * @var I18n
     */
    protected $i18n;

    /**
     * @var LinkManager
     */
    protected $linkManager;

    /**
     * @var Url
     */
    protected $urlHelper;

    public function __construct(
        I18n $i18n,
        LinkManager $linkManager,
        Url $urlHelper
    ) {
        $this->i18n = $i18n;
        $this->linkManager = $linkManager;
        $this->urlHelper = $urlHelper;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * @deprecated Since Omeka v3.0 Use toLaminas() instead.
     */
    public function toZend(SiteRepresentation $site, ?array $menu = null): array
    {
        return $this->toLaminas($site, $menu);
    }

    /**
     * Translate Omeka site navigation or any other menu to Laminas Navigation format.
     *
     * @param $options
     * - activeUrl (null|array|string|bool) Set the active url.
     *   - null (default): use the Laminas mechanism (compare with route);
     *   - true: use current url;
     *   - false: no active page;
     *   - string: If a url is set (generally a relative one), it will be
     *     checked against the real url;
     *   - array: when an array with keys "type" and "data" is set, a quick
     *     check is done against the menu element.
     * - maxDepthInactive (null|int) Should be lesser than maxDepth.
     *
     * @todo Use only the laminas mechanism to manage active url.
     */
    public function toLaminas(SiteRepresentation $site, ?array $menu = null, array $options = []): array
    {
        $activeUrl = $options['activeUrl'] ?? null;
        if ($activeUrl === true) {
            // The request uri is a relative url.
            // Same as `substr($serverUrl(true), strlen($serverUrl(false)))`.
            $activeUrl = $_SERVER['REQUEST_URI'];
        } elseif (!is_string($activeUrl) && !is_array($activeUrl) && !is_bool($activeUrl)) {
            $activeUrl = null;
        }

        // Only compare common keys.
        $compareData = function ($a, $b) {
            if (is_array($a) && is_array($b)) {
                $aa = array_intersect_key($a, $b);
                $bb = array_intersect_key($b, $a);
                // Don't check order or types: ids may be saved as string.
                // @todo Make integer the ids of menus to speed up and improve comparison.
                /*
                ksort($aa);
                ksort($bb);
                return $aa === $bb;
                */
                return $aa == $bb;
            }
            return $a === $b;
        };

        // Get the first active root branch.
        $activeRoot = null;

        $buildLinks = null;
        $buildLinks = function ($linksIn, $currentRootKey = null, $level = 0) use (&$buildLinks, $site, $activeUrl, &$activeRoot, $compareData) {
            $linksOut = [];
            foreach ($linksIn as $key => $data) {
                if (!$level) {
                    $currentRootKey = $key;
                }
                $linkType = $this->linkManager->get($data['type']);
                $linkData = $data['data'];
                $linksOut[$key] = method_exists($linkType, 'toLaminas') ? $linkType->toLaminas($linkData, $site) : $linkType->toZend($linkData, $site);
                $linksOut[$key]['label'] = $this->getLinkLabel($linkType, $linkData, $site);
                if ($activeUrl !== null) {
                    if (is_array($activeUrl)) {
                        if (!array_key_exists('uri', $data)
                            && $data['type'] === $activeUrl['type']
                            && $compareData($linkData, $activeUrl['data'])
                        ) {
                            $linksOut[$key]['active'] = true;
                            if (is_null($activeRoot)) {
                                $activeRoot = $currentRootKey;
                            }
                        }
                    } elseif (is_string($activeUrl)) {
                        if ($this->getLinkUrl($linkType, $data, $site) === $activeUrl) {
                            $linksOut[$key]['active'] = true;
                            if (is_null($activeRoot)) {
                                $activeRoot = $currentRootKey;
                            }
                        }
                    } elseif ($activeUrl === false) {
                        $linksOut[$key]['active'] = false;
                    }
                }
                if (isset($data['links'])) {
                    $linksOut[$key]['pages'] = $buildLinks($data['links'], $currentRootKey, $level + 1);
                }
            }
            return $linksOut;
        };
        $nav = is_null($menu) ? $site->navigation() : $menu;
        $links = $buildLinks($nav);

        $maxDepthInactive = $options['maxDepthInactive'] ?? null;

        $removeSubLinks = null;
        $removeSubLinks = function (array $link, int $level = 0) use (&$removeSubLinks, $maxDepthInactive): array {
            if ($level < $maxDepthInactive) {
                foreach ($link['pages'] ?? [] as $key => $subLink) {
                    $link['pages'][$key] = $removeSubLinks($subLink, $level + 1);
                }
            } else {
                $link['pages'] = [];
            }
            return $link;
        };

        // If there is no active url, maxDepth should be used.
        if ($links && $activeUrl && !is_null($maxDepthInactive)) {
            // Remove inactive sub-branches.
            foreach ($links as $key => &$link) {
                if ($key === $activeRoot) {
                    continue;
                }
                $link = $removeSubLinks($link);
            }
            unset($link);
        }

        if (!$links && is_null($menu)) {
            // The site must have at least one page for navigation to work.
            $links = [[
                'label' => $this->i18n->translate('Home'),
                'route' => 'site',
                'params' => [
                    'site-slug' => $site->slug(),
                ],
            ]];
        }
        return $links;
    }

    /**
     * Translate Omeka site navigation or any other menu to jsTree node format.
     */
    public function toJstree(SiteRepresentation $site, ?array $menu = null): array
    {
        $buildLinks = null;
        $buildLinks = function ($linksIn) use (&$buildLinks, $site) {
            $linksOut = [];
            foreach ($linksIn as $data) {
                $linkType = $this->linkManager->get($data['type']);
                $linkData = $data['data'];
                $linksOut[] = [
                    'text' => $this->getLinkLabel($linkType, $data['data'], $site),
                    'data' => [
                        'type' => $data['type'],
                        'data' => $linkType->toJstree($linkData, $site),
                        'url' => $this->getLinkUrl($linkType, $data, $site),
                    ],
                    'children' => $data['links'] ? $buildLinks($data['links']) : [],
                ];
            }
            return $linksOut;
        };
        $nav = is_null($menu) ? $site->navigation() : $menu;
        return $buildLinks($nav);
    }

    /**
     * Translate jsTree node format to Omeka site navigation format.
     */
    public function fromJstree(?array $jstree): array
    {
        if (is_null($jstree)) {
            return [];
        }
        $buildPages = null;
        $buildPages = function ($pagesIn) use (&$buildPages) {
            $pagesOut = [];
            foreach ($pagesIn as $page) {
                if (isset($page['data']['remove']) && $page['data']['remove']) {
                    // Remove pages set to be removed.
                    continue;
                }
                $pagesOut[] = [
                    'type' => $page['data']['type'],
                    'data' => $page['data']['data'],
                    'links' => $page['children'] ? $buildPages($page['children']) : [],
                ];
            }
            return $pagesOut;
        };
        return $buildPages($jstree);
    }

    /**
     * Get the label for a link.
     *
     * User-provided labels should be used as-is, while system-provided "backup" labels
     * should be translated.
     */
    public function getLinkLabel(LinkInterface $linkType, array $data, SiteRepresentation $site): string
    {
        $label = $linkType->getLabel($data, $site);
        return is_null($label) || $label === ''
            ? $this->i18n->translate($linkType->getName())
            : $label;
    }

    /**
     * Get the url for a link.
     */
    public function getLinkUrl(LinkInterface $linkType, array $data, SiteRepresentation $site): string
    {
        static $urls = [];

        if (array_key_exists('uri', $data)) {
            return (string) $data['uri'];
        }

        $serial = serialize($data);
        if (isset($urls[$serial])) {
            return $urls[$serial];
        }

        $linkLaminas = method_exists($linkType, 'toLaminas') ? $linkType->toLaminas($data['data'], $site) : $linkType->toZend($data['data'], $site);
        if (empty($linkLaminas['route'])) {
            $urls[$serial] = '';
        } else {
            $urlRoute = $linkLaminas['route'];
            $urlParams = empty($linkLaminas['params']) ? [] : $linkLaminas['params'];
            $urlParams['site-slug'] = $site->slug();
            $urlOptions = empty($linkLaminas['query']) ? [] : ['query' => $linkLaminas['query']];
            $urls[$serial] = $this->urlHelper->__invoke($urlRoute, $urlParams, $urlOptions);
        }
        return $urls[$serial];
    }
}
