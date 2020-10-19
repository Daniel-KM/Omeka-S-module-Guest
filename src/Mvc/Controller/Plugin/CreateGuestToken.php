<?php declare(strict_types=1);
namespace Guest\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Guest\Entity\GuestToken;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Entity\User;

class CreateGuestToken extends AbstractPlugin
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create and save a token.
     *
     * @todo Clear old tokens (7 days).
     *
     * @param User $user
     * @param string $identifier Another identifier than the user email. For
     * example for an update, it will be the new email.
     * @param bool $short If set, the token will be an integer of 6 numbers (for
     * example for phone confirmation). Else, it will be an alphanumeric code
     * (for email confirmation, default).
     * @return \Guest\Entity\GuestToken
     */
    public function __invoke(User $user, $identifier = null, $short = false)
    {
        $entityManager = $this->getEntityManager();
        $repository = $entityManager->getRepository(GuestToken::class);

        if (PHP_VERSION_ID < 70000) {
            $tokenString = $short
                ? function () {
                    return sprintf('%06d', random_int(102030, 989796));
                }
            : function () {
                return sha1(mt_rand());
            };
        } else {
            $tokenString = $short
                // TODO Improve the quality of the token to avoid repeated number.
                ? function () {
                    return sprintf('%06d', random_int(102030, 989796));
                }
            : function () {
                return substr(str_replace(['+', '/', '-', '='], '', base64_encode(random_bytes(16))), 0, 10);
            };
        }

        $token = $tokenString();

        // Check if the token is unique (needed only for short code).
        while ($short) {
            $result = $repository->findOneBy(['token' => $token]);
            if (!$result) {
                break;
            }
            $token = $tokenString();
        }

        $identifier = $identifier ?: $user->getEmail();

        $guestToken = new GuestToken;
        $guestToken->setEmail($identifier);
        $guestToken->setUser($user);
        $guestToken->setToken($token);

        if (!$user->getId()) {
            $entityManager->persist($user);
        }
        $entityManager->persist($guestToken);
        $entityManager->flush();

        return $guestToken;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }
}
