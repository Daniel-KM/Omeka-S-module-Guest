<?php declare(strict_types=1);

namespace Guest\Authentication\Adapter;

use Doctrine\ORM\EntityRepository;
use Laminas\Authentication\Adapter\AbstractAdapter;
use Laminas\Authentication\Result;

/**
 * Auth adapter for checking passwords through Doctrine
 *
 * Same as omeka password adapter, except a check of the guest token in order to
 * authenticate only confirmed guest users.
 *
 * @see \Omeka\Authentication\Adapter\PasswordAdapter
 */
class PasswordAdapter extends AbstractAdapter
{
    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $tokenRepository;

    public function __construct(
        EntityRepository $userRepository,
        EntityRepository $tokenRepository
    ) {
        $this->userRepository = $userRepository;
        $this->tokenRepository = $tokenRepository;
    }

    public function authenticate()
    {
        $user = $this->userRepository->findOneBy(['email' => $this->identity]);

        if (!$user || !$user->isActive()) {
            return new Result(
                Result::FAILURE_IDENTITY_NOT_FOUND,
                null,
                ['User not found.'] // @translate
            );
        }

        if ($user->getRole() == \Guest\Permissions\Acl::ROLE_GUEST) {
            $guest = $this->tokenRepository->findOneBy(['email' => $this->identity]);
            // There is no token if the guest is created directly (the role is
            // set to a user).
            if ($guest && !$guest->isConfirmed()) {
                return new Result(Result::FAILURE, null, ['Your account has not been confirmed: check your email.']); // @translate
            }
        }

        if (!$user->verifyPassword($this->credential)) {
            return new Result(
                Result::FAILURE_CREDENTIAL_INVALID,
                null,
                ['Invalid password.'] // @translate
            );
        }

        return new Result(Result::SUCCESS, $user);
    }
}
