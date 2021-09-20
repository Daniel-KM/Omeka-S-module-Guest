<?php declare(strict_types=1);
namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\SendEmail;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SendEmailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SendEmail(
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\Logger')
        );
    }
}
