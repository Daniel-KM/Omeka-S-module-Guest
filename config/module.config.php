<?php
namespace GuestUser;

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
    ],
    'view_helpers' => [
        'invokables' => [
            'guestUserWidget' => View\Helper\GuestUserWidget::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\AcceptTermsForm::class => Form\AcceptTermsForm::class,
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\EmailForm::class => Form\EmailForm::class,
        ],
        'factories' => [
            // TODO To remove after merge of pull request https://github.com/omeka/omeka-s/pull/1138.
            'Omeka\Form\UserForm' => Service\Form\UserFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'GuestUser\Controller\Site\GuestUser' => Service\Controller\Site\GuestUserControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\Acl' => Service\AclFactory::class,
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'register' => Site\Navigation\Link\Register::class,
            'login' => Site\Navigation\Link\Login::class,
            'logout' => Site\Navigation\Link\Logout::class,
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'User information',
                'route' => '/guest-user/login',
                'resource' => Controller\Site\GuestUserController::class,
                'visible' => true,
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'guest-user' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/guest-user[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'GuestUser\Controller\Site',
                                'controller' => 'GuestUser',
                                'action' => 'me',
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
    'guestuser' => [
        'config' => [
            'guestuser_capabilities' => '',
            'guestuser_short_capabilities' => '',
            'guestuser_login_text' => 'Login', // @translate
            'guestuser_register_text' => 'Register', // @translate
            'guestuser_dashboard_label' => 'My Account', // @translate
            'guestuser_open' => false,
            'guestuser_recaptcha' => false,
            'guestuser_terms_text' => 'I agree the terms and conditions.', // @translate
            'guestuser_terms_page' => 'terms-and-conditions',
            'guestuser_terms_request_regex' => '',
        ],
        'user_settings' => [
            'guestuser_agreed_terms' => false,
        ],
    ],
];
