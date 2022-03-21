<?php declare(strict_types=1);

namespace Guest\Controller\Site;

use Guest\Form\AcceptTermsForm;
use Guest\Form\EmailForm;
use Guest\Stdlib\PsrMessage;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\Container as SessionContainer;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\User;

/**
 * Manage guests pages.
 */
class GuestController extends AbstractGuestController
{
    public function logoutAction()
    {
        $auth = $this->getAuthenticationService();
        $auth->clearIdentity();

        $sessionManager = SessionContainer::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $this->messenger()->addSuccess('Successfully logged out'); // @translate

        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toUrl($this->currentSite()->url());
    }

    public function meAction()
    {
        $site = $this->currentSite();
        $user = $this->identity();

        $eventManager = $this->getEventManager();
        $partial = $this->viewHelpers()->get('partial');

        $widget = [];
        $widget['label'] = $this->translate('My Account'); // @translate
        $widget['content'] = $partial('guest/site/guest/widget/account');

        $args = $eventManager->prepareArgs(['widgets' => []]);
        $args['widgets']['account'] = $widget;

        $eventManager->triggerEvent(new MvcEvent('guest.widgets', $this, $args));

        return new ViewModel([
            'site' => $site,
            'user' => $user,
            'widgets' => $args['widgets'],
        ]);
    }

    public function updateAccountAction()
    {
        $site = $this->currentSite();

        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();
        $id = $user->getId();

        $label = $this->getOption('guest_dashboard_label')
            ?: $this->translate('My account'); // @translate

        $userRepr = $this->api()->read('users', $id)->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->getUserForm($user);
        $form->get('user-information')->populateValues($data);
        $form->get('change-password')->populateValues($data);

        // The email is updated separately for security.
        $emailField = $form->get('user-information')->get('o:email');
        $emailField->setAttribute('disabled', true);
        $emailField->setAttribute('required', false);

        $view = new ViewModel([
            'site' => $site,
            'user' => $user,
            'form' => $form,
            'label' => $label,
        ]);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        // A security.
        unset($postData['user-information']['o:id']);
        unset($postData['user-information']['o:email']);
        unset($postData['user-information']['o:role']);
        unset($postData['user-information']['o:is_active']);
        unset($postData['edit-keys']);
        $postData['user-information'] = array_replace(
            $data,
            array_intersect_key($postData['user-information'], $data)
        );
        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Password invalid'); // @translate
            return $view;
        }
        $values = $form->getData();
        $response = $this->api($form)->update('users', $user->getId(), $values['user-information']);

        // Stop early if the API update fails.
        if (!$response) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        $successMessages = [];
        $successMessages[] = 'Your modifications have been saved.'; // @translate

        // The values were filtered: no hack is possible with added values.
        if (!empty($values['user-settings'])) {
            $userSettings = $this->userSettings();
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        // Manage old and new user forms (Omeka 1.4).
        if (array_key_exists('password', $values['change-password'])) {
            $passwordValues = $values['change-password'];
        } else {
            $passwordValues = $values['change-password']['password-confirm'];
        }
        if (!empty($passwordValues['password'])) {
            // TODO Add a current password check when update account. Check is done in Omeka 1.4.
            // if (!$user->verifyPassword($passwordValues['current-password'])) {
            //     $this->messenger()->addError('The current password entered was invalid'); // @translate
            //     return $view;
            // }
            $user->setPassword($passwordValues['password']);
            $successMessages[] = 'Password successfully changed'; // @translate
        }

        $this->entityManager->flush();

        foreach ($successMessages as $message) {
            $this->messenger()->addSuccess($message);
        }
        return $view;
    }

    public function updateEmailAction()
    {
        $site = $this->currentSite();

        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();

        $form = $this->getForm(EmailForm::class, []);
        $form->populateValues(['o:email' => $user->getEmail()]);

        $view = new ViewModel([
            'site' => $site,
            'user' => $user,
            'form' => $form,
        ]);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Email invalid'); // @translate
            return $view;
        }

        $values = $form->getData();
        $email = $values['o:email'];

        if ($email === $user->getEmail()) {
            $this->messenger()->addWarning(new PsrMessage('The new email is the same than the current one.')); // @translate
            return $view;
        }

        $existUser = $this->getEntityManager()->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        if ($existUser) {
            // Avoid a hack of the database.
            sleep(2);
            $this->messenger()->addError(new PsrMessage('The email "{user_email}" is not yours.', ['user_email' => $email])); // @translate
            return $view;
        }

        $guestToken = $this->createGuestToken($user, $email);
        $message = $this->prepareMessage('update-email', [
            'user_email' => $email,
            'user_name' => $user->getName(),
            'token' => $guestToken,
        ]);
        $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[Guest] ' . $message);
            return $view;
        }

        $message = new PsrMessage('Check your email "{user_email}" to confirm the change.', ['user_email' => $email]); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
    }

    public function acceptTermsAction()
    {
        $skip = (bool) $this->settings()->get('guest_terms_skip');
        if ($skip) {
            // $this->userSettings()->set('guest_agreed_terms', true);
            return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
        }

        $userSettings = $this->userSettings();
        $agreed = $userSettings->get('guest_agreed_terms');
        if ($agreed) {
            $message = new PsrMessage('You already agreed the terms and conditions.'); // @translate
            $this->messenger()->addSuccess($message);
            return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
        }

        $site = $this->currentSite();

        $forced = $this->settings()->get('guest_terms_force_agree');

        /** @var \Guest\Form\AcceptTermsForm $form */
        // $form = $this->getForm(AcceptTermsForm::class, null, ['forced' => $forced]);
        $form = new AcceptTermsForm();
        $form->setOption('forced', $forced);
        $form->init();

        $user = $this->identity();
        $text = $this->getOption('guest_terms_text');
        $page = $this->getOption('guest_terms_page');

        $view = new ViewModel([
            'site' => $site,
            'user' => $user,
            'form' => $form,
            'text' => $text,
            'page' => $page,
        ]);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Form invalid'); // @translate
            return $view;
        }

        $data = $form->getData();
        $accept = (bool) $data['guest_agreed_terms'];
        $userSettings->set('guest_agreed_terms', $accept);

        if (!$accept) {
            if ($forced) {
                $message = new PsrMessage('The access to this website requires you accept the current terms and conditions.'); // @translate
                $this->messenger()->addError($message);
                return $view;
            }
            return $this->redirect()->toRoute('site/guest/guest', ['action' => 'logout'], [], true);
        }

        $message = new PsrMessage('Thanks for accepting the terms and condtions.'); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirectToAdminOrSite();
    }
}
