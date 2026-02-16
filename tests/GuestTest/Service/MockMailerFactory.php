<?php declare(strict_types=1);

namespace GuestTest\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mail\Transport\Factory as TransportFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MockMailerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $transport = TransportFactory::create([]);
        $viewHelpers = $services->get('ViewHelperManager');
        $entityManager = $services->get('Omeka\EntityManager');

        return new MockMailer($transport, $viewHelpers, $entityManager, []);
    }
}
