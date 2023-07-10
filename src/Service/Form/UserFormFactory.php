<?php declare(strict_types=1);

namespace Guest\Service\Form;

use Guest\Form\UserForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new UserForm(null, $options);
        $form->setAcl($services->get('Omeka\Acl'));
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        $form->setSettings($services->get('Omeka\Settings'));
        $form->setEventManager($services->get('EventManager'));
        $form->setBrowseService($services->get('Omeka\Browse'));
        return $form;
    }
}
