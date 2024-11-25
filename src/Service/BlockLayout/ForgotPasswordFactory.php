<?php declare(strict_types=1);

namespace Guest\Service\BlockLayout;

use Guest\Site\BlockLayout\ForgotPassword;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ForgotPasswordFactory implements FactoryInterface
{
    /**
     * Create the ForgotPassword block layout service.
     *
     * @param ContainerInterface $services
     * @return ForgotPassword
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');

        return new ForgotPassword(
            $services->get('FormElementManager'),
            $plugins->get('messenger')
        );
    }
}
