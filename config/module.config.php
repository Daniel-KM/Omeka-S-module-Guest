<?php declare(strict_types=1);

namespace Guest;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'guestWidget' => View\Helper\GuestWidget::class,
            'userBar' => View\Helper\UserBar::class,
            // Required to manage PsrMessage.
            'messages' => View\Helper\Messages::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\AcceptTermsForm::class => Form\AcceptTermsForm::class,
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\EmailForm::class => Form\EmailForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\Element\OptionalSiteSelect::class => Service\Form\Element\OptionalSiteSelectFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Site\AnonymousController::class => Service\Controller\Site\AnonymousControllerFactory::class,
            Controller\Site\GuestController::class => Service\Controller\Site\GuestControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'createGuestToken' => Service\ControllerPlugin\CreateGuestTokenFactory::class,
            'sendEmail' => Service\ControllerPlugin\SendEmailFactory::class,
            'userSites' => Service\ControllerPlugin\UserSitesFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
        ],
        'invokables' => [
            'Guest\MvcListeners' => Mvc\MvcListeners::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'login' => Site\Navigation\Link\Login::class,
            'loginBoard' => Site\Navigation\Link\LoginBoard::class,
            'logout' => Site\Navigation\Link\Logout::class,
            'register' => Site\Navigation\Link\Register::class,
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'User information', // @translate
                'route' => 'site/guest',
                'controller' => Controller\Site\GuestController::class,
                'action' => 'me',
                'useRouteMatch' => true,
                'visible' => false,
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'guest' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/guest',
                            'defaults' => [
                                '__NAMESPACE__' => 'Guest\Controller\Site',
                                'controller' => Controller\Site\GuestController::class,
                                'action' => 'me',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'anonymous' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        // "confirm" must be after "confirm-email" because regex is ungreedy.
                                        'action' => 'login|confirm-email|confirm|validate-email|forgot-password|stale-token|auth-error|register',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Guest\Controller\Site',
                                        'controller' => Controller\Site\AnonymousController::class,
                                        'controller' => 'AnonymousController',
                                        'action' => 'login',
                                    ],
                                ],
                            ],
                            'guest' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => 'me|logout|update-account|update-email|accept-terms',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Guest\Controller\Site',
                                        'controller' => Controller\Site\GuestController::class,
                                        'action' => 'me',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'listeners' => [
        'Guest\MvcListeners',
    ],
    'guest' => [
        'settings' => [
            'guest_open' => 'moderate',
            'guest_notify_register' => [],
            'guest_default_sites' => [],
            'guest_recaptcha' => false,
            'guest_terms_skip' => false,
            'guest_terms_request_regex' => '',
            'guest_terms_force_agree' => true,
            // Fields default when no site setting.
            'guest_login_text' => 'Login', // @translate
            'guest_register_text' => 'Register', // @translate
            'guest_dashboard_label' => 'My dashboard', // @translate
            'guest_capabilities' => '',
            // From Omeka classic, but not used.
            // TODO Remove option "guest_short_capabilities" or implement it.
            'guest_short_capabilities' => '',
            'guest_message_confirm_email_subject' => '[{site_title}] Confirm email', // @translate
            'guest_message_confirm_email' => '<p>Hi {user_name},</p>
<p>You have registered for an account on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>Please confirm your registration by following this link: <a href="{token_url}"> {token_url}</a></p>
<p>If you did not request to join {main_title} / {site_title}, please disregard this email.</p>', // @translate
            'guest_message_confirm_registration_email_subject' => '[{site_title}] Account open', // @translate
            'guest_message_confirm_registration_email' => '<p>Hi {user_name},</p>
<p>We are happy to open your account on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>You can now login and discover the site.</p>', // @translate
            'guest_message_update_email_subject' => '[{site_title}] Confirm email', // @translate
            'guest_message_update_email' => '<p>Hi {user_name},</p>
<p>You have requested to update email on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>Please confirm your email by following this link: <a href="{token_url}">{token_url}</a>.</p>
<p>If you did not request to update your email on {main_title}, please disregard this email.</p>', // @translate
            'guest_message_confirm_email_site' => 'Your email "{user_email}" is confirmed for {site_title}.', // @translate
            'guest_message_confirm_register_site' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.', // @translate
            'guest_message_confirm_register_moderate_site' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request and we have confirmed it, you will be able to log in.', // @translate
            'guest_terms_text' => 'I agree the terms and conditions.', // @translate
            'guest_terms_page' => 'terms-and-conditions',
            'guest_redirect' => 'site',
        ],
        'site_settings' => [
            'guest_notify_register' => [],
            'guest_login_text' => 'Login', // @translate
            'guest_register_text' => 'Register', // @translate
            'guest_dashboard_label' => 'My dashboard', // @translate
            'guest_capabilities' => '',
            // From Omeka classic, but not used.
            // TODO Remove option "guest_short_capabilities" or implement it.
            'guest_short_capabilities' => '',
            'guest_message_confirm_email_subject' => '[{site_title}] Confirm email', // @translate
            'guest_message_confirm_email' => '<p>Hi {user_name},</p>
<p>You have registered for an account on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>Please confirm your registration by following this link: <a href="{token_url}"> {token_url}</a></p>
<p>If you did not request to join {main_title} / {site_title}, please disregard this email.</p>', // @translate
            'guest_message_confirm_registration_email_subject' => '[{site_title}] Account open', // @translate
            'guest_message_confirm_registration_email' => '<p>Hi {user_name},</p>
<p>We are happy to open your account on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>You can now login and discover the site.</p>', // @translate
            'guest_message_update_email_subject' => '[{site_title}] Confirm email', // @translate
            'guest_message_update_email' => '<p>Hi {user_name},</p>
<p>You have requested to update email on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>Please confirm your email by following this link: <a href="{token_url}">{token_url}</a>.</p>
<p>If you did not request to update your email on {main_title}, please disregard this email.</p>', // @translate
            'guest_message_confirm_email_site' => 'Your email "{user_email}" is confirmed for {site_title}.', // @translate
            'guest_message_confirm_register_site' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.', // @translate
            'guest_message_confirm_register_moderate_site' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request and we have confirmed it, you will be able to log in.', // @translate
            'guest_terms_text' => 'I agree the terms and conditions.', // @translate
            'guest_terms_page' => 'terms-and-conditions',
            'guest_redirect' => 'site',
        ],
        'user_settings' => [
            'guest_site' => null,
            'guest_agreed_terms' => false,
        ],
    ],
];
