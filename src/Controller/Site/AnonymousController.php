<?php declare(strict_types=1);
namespace Guest\Controller\Site;

use Guest\Entity\GuestToken;
use Guest\Stdlib\PsrMessage;
use Laminas\Session\Container as SessionContainer;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\User;
use Omeka\Form\ForgotPasswordForm;
use Omeka\Form\LoginForm;

/**
 * Manage anonymous visitor pages.
 */
class AnonymousController extends AbstractGuestController
{
    public function loginAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $auth = $this->getAuthenticationService();

        $view = new ViewModel;

        /** @var LoginForm $form */
        $form = $this->getForm(LoginForm::class);
        $view->setVariable('form', $form);

        $view->setVariable('form', $form);

        if (!$this->checkPostAndValidForm($form)) {
            $email = $this->params()->fromPost('email') ?: $this->params()->fromQuery('email');
            if ($email) {
                $form->get('email')->setValue($email);
            }
            return $view;
        }

        $validatedData = $form->getData();
        $sessionManager = SessionContainer::getDefaultManager();
        $sessionManager->regenerateId();

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($validatedData['email']);
        $adapter->setCredential($validatedData['password']);
        $result = $auth->authenticate();
        if (!$result->isValid()) {
            // Check if the user is under moderation in order to add a message.
            if (!$this->isOpenRegister()) {
                $entityManager = $this->getEntityManager();
                /** @var \Omeka\Entity\User $user */
                $user = $entityManager->getRepository(User::class)->findOneBy([
                    'email' => $validatedData['email'],
                ]);
                if ($user) {
                    $guestToken = $entityManager->getRepository(GuestToken::class)
                        ->findOneBy(['email' => $validatedData['email']], ['id' => 'DESC']);
                    if (empty($guestToken) || $guestToken->isConfirmed()) {
                        if (!$user->isActive()) {
                            $this->messenger()->addError('Your account is under moderation for opening.'); // @translate
                            return $view;
                        }
                    } else {
                        $this->messenger()->addError('Check your email to confirm your registration.'); // @translate
                        return $view;
                    }
                }
            }
            $this->messenger()->addError(implode(';', $result->getMessages()));
            return $view;
        }

        $this->messenger()->addSuccess('Successfully logged in'); // @translate
        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.login', $auth->getIdentity());

        return $this->redirectToAdminOrSite();
    }

    public function registerAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $user = new User();
        $user->setRole(\Guest\Permissions\Acl::ROLE_GUEST);

        $form = $this->getUserForm($user);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        // TODO Add password required only for login.
        $values = $form->getData();

        // Manage old and new user forms (Omeka 1.4).
        if (array_key_exists('password', $values['change-password'])) {
            if (empty($values['change-password']['password'])) {
                $this->messenger()->addError('A password must be set.'); // @translate
                return $view;
            }
            $password = $values['change-password']['password'];
        } else {
            if (empty($values['change-password']['password-confirm']['password'])) {
                $this->messenger()->addError('A password must be set.'); // @translate
                return $view;
            }
            $password = $values['change-password']['password-confirm']['password'];
        }

        $userInfo = $values['user-information'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = \Guest\Permissions\Acl::ROLE_GUEST;
        $userInfo['o:is_active'] = false;
        $response = $this->api()->create('users', $userInfo);
        if (!$response) {
            $entityManager = $this->getEntityManager();
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if ($user) {
                $guestToken = $entityManager->getRepository(GuestToken::class)
                    ->findOneBy(['email' => $userInfo['o:email']], ['id' => 'DESC']);
                if (empty($guestToken) || $guestToken->isConfirmed()) {
                    $this->messenger()->addError('Already registered.'); // @translate
                } else {
                    $this->messenger()->addError('Check your email to confirm your registration.'); // @translate
                }
                return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], true);
            }

            $this->messenger()->addError('Unknown error.'); // @translate
            return $view;
        }

        /** @var \Omeka\Entity\User $user */
        $user = $response->getContent()->getEntity();
        $user->setPassword($password);
        $user->setRole(\Guest\Permissions\Acl::ROLE_GUEST);
        // The account is active, but not confirmed, so login is not possible.
        // Guest has no right to set active his account.
        $isOpenRegister = $this->isOpenRegister();
        $user->setIsActive($isOpenRegister);

        $id = $user->getId();
        $userSettings = $this->userSettings();
        if (!empty($values['user-settings'])) {
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        // Save the site on which the user registered.
        $userSettings->set('guest_site', $this->currentSite()->id(), $id);

        $emails = $this->getOption('guest_notify_register');
        if ($emails) {
            $message = new PsrMessage(
                'A new user is registering: {user_email} ({url}).', // @translate
                [
                    'user_email' => $user->getEmail(),
                    'url' => $this->url()->fromRoute('admin/id', ['controller' => 'user', 'id' => $user->getId()], ['force_canonical' => true]),
                ]
            );
            $result = $this->sendEmail($emails, $this->translate('[Omeka Guest] New registration'), $message); // @translate
            if (!$result) {
                $message = new PsrMessage('An error occurred when the notification email was sent.'); // @translate
                $this->messenger()->addError($message);
                $this->logger()->err('[Guest] ' . $message);
                return $view;
            }
        }

        $guestToken = $this->createGuestToken($user);
        $message = $this->prepareMessage('confirm-email', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'token' => $guestToken,
        ]);
        $result = $this->sendEmail($user->getEmail(), $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[Guest] ' . $message);
            return $view;
        }

        $message = $this->isOpenRegister()
            ? $this->getOption('guest_message_confirm_register_site')
            : $this->getOption('guest_message_confirm_register_moderate_site');
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], [], true);
    }

    public function confirmAction()
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();
        $guestToken = $entityManager->getRepository(GuestToken::class)->findOneBy(['token' => $token]);
        if (empty($guestToken)) {
            $this->messenger()->addError($this->translate('Invalid token stop')); // @translate
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $guestToken->setConfirmed(true);
        $entityManager->persist($guestToken);
        $user = $entityManager->find(User::class, $guestToken->getUser()->getId());

        $isOpenRegister = $this->isOpenRegister();

        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setIsActive($isOpenRegister);
        $entityManager->persist($user);
        $entityManager->flush();

        $currentSite = $this->currentSite();
        $siteTitle = $currentSite->title();
        if ($isOpenRegister) {
            $body = new PsrMessage('Thanks for joining {site_title}! You can now log in using the password you chose.', // @translate
                ['site_title' => $siteTitle]);
            $this->messenger()->addSuccess($body);
            $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                'site-slug' => $currentSite->slug(),
                'action' => 'login',
            ]);
            return $this->redirect()->toUrl($redirectUrl);
        }

        $body = new PsrMessage('Thanks for joining {site_title}! Your registration is under moderation. See you soon!', // @translate
            ['site_title' => $siteTitle]);
        $this->messenger()->addSuccess($body);
        $redirectUrl = $currentSite->url();
        return $this->redirect()->toUrl($redirectUrl);
    }

    public function confirmEmailAction()
    {
        return $this->confirmEmail(false);
    }

    public function validateEmailAction()
    {
        return $this->confirmEmail(true);
    }

    protected function confirmEmail($isUpdate)
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();

        $siteTitle = $this->currentSite()->title();

        $guestToken = $entityManager->getRepository(GuestToken::class)->findOneBy(['token' => $token]);
        if (empty($guestToken)) {
            $message = new PsrMessage('Invalid token: your email was not confirmed for {site_title}.', // @translate
                ['site_title' => $siteTitle]);

            $this->messenger()->addError($message); // @translate
            if ($this->isUserLogged()) {
                $redirectUrl = $this->url()->fromRoute('site/guest/guest', [
                    'site-slug' => $this->currentSite()->slug(),
                    'action' => 'update-email',
                ]);
            } else {
                $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                    'site-slug' => $this->currentSite()->slug(),
                    'action' => 'login',
                ]);
            }
            return $this->redirect()->toUrl($redirectUrl);
        }

        $guestToken->setConfirmed(true);
        $entityManager->persist($guestToken);

        // Validate all tokens with the same email to avoid issues when the
        // email is checked after login.
        $email = $guestToken->getEmail();
        $guestTokens = $entityManager->getRepository(GuestToken::class)->findBy(['email' => $email]);
        foreach ($guestTokens as $gToken) {
            $gToken->setConfirmed(true);
            $entityManager->persist($gToken);
        }

        $user = $entityManager->find(User::class, $guestToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setEmail($email);
        $entityManager->persist($user);
        $entityManager->flush();

        // The message is not the same for an existing user and a new user.
        $message = $isUpdate
            ? 'Your email "{user_email}" is confirmed for {site_title}.'
            : $this->getOption('guest_message_confirm_email_site');
        $message = new PsrMessage($message, ['user_email' => $email, 'site_title' => $siteTitle]);
        $this->messenger()->addSuccess($message);

        if ($this->isUserLogged()) {
            $redirectUrl = $this->url()->fromRoute('site/guest', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'me',
            ]);
        } else {
            $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'login',
            ]);
        }
        return $this->redirect()->toUrl($redirectUrl);
    }

    public function forgotPasswordAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $form = $this->getForm(ForgotPasswordForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->getRequest()->getPost();
        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addError('Activation unsuccessful'); // @translate
            return $view;
        }

        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $data['email'],
                'isActive' => true,
            ]);
        if ($user) {
            $entityManager->persist($user);
            $passwordCreation = $entityManager
                ->getRepository(\Omeka\Entity\PasswordCreation::class)
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $entityManager->remove($passwordCreation);
                $entityManager->flush();
            }
            $this->mailer()->sendResetPassword($user);
        }

        $this->messenger()->addSuccess('Check your email for instructions on how to reset your password'); // @translate

        // Bypass settings.
        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toRoute('site', [], true);
    }

    public function staleTokenAction(): void
    {
        $auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
        $auth->clearIdentity();
    }

    public function authErrorAction()
    {
        return new ViewModel;
    }

    /**
     * Check if a user is logged.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return bool
     */
    protected function isUserLogged()
    {
        return $this->getAuthenticationService()->hasIdentity();
    }

    /**
     * Check if the registering is open or moderated.
     *
     *  @return bool True if open, false if moderated (or closed).
     */
    protected function isOpenRegister()
    {
        return $this->settings()->get('guest_open') === 'open';
    }

    protected function checkPostAndValidForm(\Laminas\Form\Form $form)
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if (!$form->isValid()) {
            $this->messenger()->addError('Email or password invalid'); // @translate
            return false;
        }
        return true;
    }
}
