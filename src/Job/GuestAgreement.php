<?php declare(strict_types=1);

namespace Guest\Job;

use Guest\Permissions\Acl;
use Omeka\Job\AbstractJob;

class GuestAgreement extends AbstractJob
{
    public function perform(): void
    {
        /**
         * @var \Laminas\Log\LoggerInterface $logger
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');

        $agreement = $this->getArg('agreement');

        switch ($agreement) {
            case 'unset':
                $this->resetAgreementsBySql(false);
                $logger->notice(
                    'All guests must agreed the terms one more time.' // @translate
                );
                break;
            case 'set':
                $this->resetAgreementsBySql(true);
                $logger->notice(
                    'All guests agreed the terms.' // @translate
                );
                break;
            default:
                $logger->warn(
                    'No process can be done without settings.' // @translate
                );
                break;
        }
    }

    /**
     * Reset all guest agreements.
     */
    protected function resetAgreements(bool $reset): void
    {
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $entityManager = $services->get('Omeka\EntityManager');
        $guests = $entityManager->getRepository(\Omeka\Entity\User::class)
            ->findBy(['role' => Acl::ROLE_GUEST]);
        foreach ($guests as $user) {
            $userSettings->setTargetId($user->getId());
            $userSettings->set('guest_agreed_terms', $reset);
        }
    }

    /**
     * Reset all guest agreements via sql (quicker for big base).
     */
    protected function resetAgreementsBySql(bool $reset): void
    {
        $reset = $reset ? 'true' : 'false';
        $sql = <<<SQL
DELETE FROM user_setting
WHERE id="guest_agreed_terms";

INSERT INTO user_setting (id, user_id, value)
SELECT "guest_agreed_terms", user.id, "$reset"
FROM user
WHERE role="guest";
SQL;
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->executeStatement($sql);
        }
    }
}
