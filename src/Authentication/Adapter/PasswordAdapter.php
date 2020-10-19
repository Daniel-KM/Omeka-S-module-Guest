<?php declare(strict_types=1);
namespace Guest\Authentication\Adapter;

use Laminas\Authentication\Result;
use Omeka\Authentication\Adapter\PasswordAdapter as OmekaPasswordAdapter;

/**
 * Auth adapter for checking passwords through Doctrine
 *
 * Same as omeka password manager, except a check of the guest token.
 */
class PasswordAdapter extends OmekaPasswordAdapter
{
    protected $token_repository;

    public function authenticate()
    {
        $user = $this->repository->findOneBy(['email' => $this->identity]);

        if (!$user || !$user->isActive()) {
            return new Result(
                Result::FAILURE_IDENTITY_NOT_FOUND,
                null,
                ['User not found.'] // @translate
            );
        }

        if ($user->getRole() == \Guest\Permissions\Acl::ROLE_GUEST) {
            $guest = $this->token_repository->findOneBy(['email' => $this->identity]);
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

    public function setTokenRepository($token_repository): void
    {
        $this->token_repository = $token_repository;
    }
}
