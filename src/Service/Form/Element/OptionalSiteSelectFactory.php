<?php declare(strict_types=1);

namespace Guest\Service\Form\Element;

use Guest\Form\Element\OptionalSiteSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OptionalSiteSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new OptionalSiteSelect;
        $element
            ->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
