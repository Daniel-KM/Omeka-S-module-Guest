<?php declare(strict_types=1);

namespace Guest\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Guest'; // @translate

    protected $elementGroups = [
        'guest' => 'Guest', // @translate
    ];

    public function init(): void
    {
        // Fields default when no site setting.

        $this
            ->setAttribute('id', 'guest')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'guest_open',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Registration', // @translate
                    'info' => 'Allow guest registration without administrator approval. The link to use is "/s/my-site/guest/register".', // @translate
                    'value_options' => [
                        'open' => 'Open to everyone', // @translate
                        'moderate' => 'Open with moderation', // @translate
                        'closed' => 'Closed to visitors', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest_open',
                ],
            ])

            ->add([
                'name' => 'guest_notify_register',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default emails to notify registrations', // @translate
                    'info' => 'The list of emails to notify when a user registers, one by row.', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'placeholder' => 'contact@example.org
info@example2.org',
                ],
            ])

            ->add([
                'name' => 'guest_default_sites',
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Add new users to sites', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'guest_default_sites',
                    'class' => 'chosen-select',
                    'required' => false,
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_recaptcha',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Require ReCaptcha', // @translate
                    'info' => 'Check this to require passing a ReCaptcha test when registering', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_recaptcha',
                ],
            ])

            ->add([
                'name' => 'guest_terms_skip',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Skip terms agreement', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_skip',
                ],
            ])

            ->add([
                'name' => 'guest_terms_request_regex',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Pages not to redirect', // @translate
                    'info' => 'Allows to keep some pages available when terms are not yet agreed. Default pages are included (logout, terms page…). This is a regex, with "~" delimiter, checked against the end of the url.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_request_regex',
                ],
            ])

            ->add([
                'name' => 'guest_terms_force_agree',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Force terms agreement', // @translate
                    'info' => 'If unchecked, the user will be logged out if terms are not accepted.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_force_agree',
                ],
            ])

            // Fields default when no site setting.

            ->add([
                'name' => 'guest_login_text',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default login text', // @translate
                    'info' => 'The text to use for the "Login" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_text',
                ],
            ])

            ->add([
                'name' => 'guest_register_text',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default register text', // @translate
                    'info' => 'The text to use for the "Register" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_text',
                ],
            ])

            ->add([
                'name' => 'guest_dashboard_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default dashboard label', // @translate
                    'info' => 'The text to use for the label on the user’s dashboard', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_dashboard_label',
                ],
            ])

            ->add([
                'name' => 'guest_capabilities',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default registration features', // @translate
                    'info' => 'Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest, please give them guidance about what they can and cannot do.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_capabilities',
                ],
            ])

            /* // From Omeka classic, but not used.
            ->add([
                'name' => 'guest_short_capabilities',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default short registration features', // @translate
                    'info' => 'Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_short_capabilities',
                ],
            ])
            */

            ->add([
                'name' => 'guest_message_confirm_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default subject of the email sent to confirm email', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email_subject',
                    'placeholder' => '[{site_title}] Confirm email', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default email sent to confirm registration', // @translate
                    'info' => 'The text of the email to confirm the registration and to send the token.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email',
                    'placeholder' => 'Hi {user_name},
You have registered for an account on {main_title} / {site_title} ({site_url}).
Please confirm your registration by following this link: {token_url}.
If you did not request to join {main_title} please disregard this email.', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_registration_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default subject of the email sent to confirm registration after moderation', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_registration_email_subject',
                    'placeholder' => '[{site_title}] Account open', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_registration_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default email sent to confirm registration after moderation', // @translate
                    'info' => 'When the moderation is set, the user may be informed automatically when the admin activates account (check box in second user tab).', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_registration_email',
                    'placeholder' => 'Hi {user_name},
We are happy to open your account on {main_title} / {site_title} ({site_url}).
You can now login and discover the site.', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_update_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default subject of email sent to update email', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_update_email_subject',
                    'placeholder' => '[{site_title}] Confirm email', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_update_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default email sent to update email', // @translate
                    'info' => 'The text of the email sent when the user wants to update it.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_update_email',
                    'placeholder' => 'Hi {user_name},
You have requested to update email on {main_title} / {site_title} ({site_url}).
Please confirm your email by following this link: {token_url}.
If you did not request to update your email on {main_title}, please disregard this email.', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_email_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default message to confirm email on the page', // @translate
                    'info' => 'The message to  display after confirmation of a mail.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email_site',
                    'required' => false,
                    'placeholder' => 'Your email "{user_email}" is confirmed for {site_title}.', // @translate
                    'rows' => 3,
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_register_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default message to confirm registration on the page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_register_site',
                    'required' => false,
                    'placeholder' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.', // @translate
                    'rows' => 3,
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_register_moderate_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default message to confirm registration and moderation on the page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_register_moderate_site',
                    'required' => false,
                    'placeholder' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request and we have confirmed it, you will be able to log in.', // @translate
                    'rows' => 3,
                ],
            ])

            ->add([
                'name' => 'guest_terms_text',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default text for terms and conditions', // @translate
                    'info' => 'The text to display to accept condtions.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_text',
                ],
            ])

            ->add([
                'name' => 'guest_terms_page',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default page slug of the terms and conditions', // @translate
                    'info' => 'If the text is on a specific page, or for other usage.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_page',
                ],
            ])

            ->add([
                'name' => 'guest_redirect',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Default redirect page after login', // @translate
                    'info' => 'Set "home" for main home page (admin or public), "site" for the current site home, "me" for guest account, or any path starting with "/", including "/" itself for main home page.',
                ],
                'attributes' => [
                    'id' => 'guest_redirect',
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'guest_append_links_to_login_view',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Append cas/sso login links to login page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_append_links_to_login_view',
                ],
            ])

            // Specific to api.

            ->add([
                'name' => 'guest_register_site',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Requires a site to register via api', // @translate
                    'info' => 'If checked, a site id or slug will be required when registering via api. Note: when this setting is set, all previous users must be added to a site.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_site',
                ],
            ])
            ->add([
                'name' => 'guest_register_email_is_valid',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Validate email set by api', // @translate
                    'info' => 'If checked, the user won’t have to validate his email, so he will be able to login directly.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_email_is_valid',
                ],
            ])
            ->add([
                'name' => 'guest_login_roles',
                'type' => CommonElement\OptionalRoleSelect::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Roles that can login via api', // @translate
                    'info' => 'To allow full access via api increases risks of intrusion.', // @translate
                    'empty_option' => '',
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'guest_login_roles',
                    'multiple' => true,
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select roles…', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_login_session',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Create a local session cookie for api', // @translate
                    'info' => 'If checked, a session cookie will be created, so the user will be able to login in Omeka from an other web app.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_session',
                ],
            ])
            ->add([
                'name' => 'guest_cors',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Limit access to api to these domains (cors)', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_cors',
                    'rows' => 5,
                    'placeholder' => 'http://example.org
https://example.org',
                ],
            ])
        ;
    }
}
