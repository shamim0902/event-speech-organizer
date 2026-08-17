<?php

namespace EventSpeechOrganizer\Classes\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

use EventSpeechOrganizer\Classes\ApplicantModel;
use FluentForm\App\Http\Controllers\IntegrationManagerController;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

/**
 * Fluent Forms integration: turns a form submission into a speaker applicant.
 *
 * Modelled on FluentFormPro's UserRegistration integration, which is the
 * reference for an integration with no remote service — there are no API
 * credentials, so there is no global settings screen and isConfigured() is
 * simply true.
 *
 * This class extends a Fluent Forms class, so it must only ever be loaded once
 * Fluent Forms itself has booted. It is instantiated on `fluentform/loaded`.
 */
class FluentFormIntegration extends IntegrationManagerController
{
    public $category = 'wp_core';

    public $disableGlobalSettings = 'yes';

    /**
     * Applicant columns offered in the field map, and their labels.
     *
     * `status` and `date` are deliberately absent: a new submission is always
     * a pending application, and its date is the submission time.
     */
    private static $mappableFields = array(
        'name'        => 'Name',
        'email'       => 'Email',
        'phone'       => 'Phone',
        'username'    => 'WordPress.org username',
        'social'      => 'Social handles',
        'comment'     => 'Bio',
        'topic'       => 'Topic title',
        'type'        => 'Talk type',
        'description' => 'Description',
        'cospeakers'  => 'Co-speakers',
        'audience'    => 'Audience',
        'experience'  => 'Experience',
        'question'    => 'Question',
    );

    /**
     * Feed keys are prefixed because the feed itself already owns `name`
     * (the feed's own title) and `enabled`.
     */
    const FIELD_PREFIX = 'applicant_';

    public function __construct($app)
    {
        parent::__construct(
            $app,
            'Event Speech Organizer',
            'EventSpeechOrganizer',
            '_event_speech_organizer_integration_settings',
            'event_speech_organizer_feeds',
            20
        );

        $this->logo = EVENT_SPEECH_ORGANIZER_URL . 'assets/images/logo.png';
        $this->description = 'Add form submissions as speaker applicants in Event Speech Organizer.';

        $this->registerAdminHooks();

        // Run inline instead of through the async scheduler, so the applicant
        // shows up the moment the form is submitted.
        add_filter('fluentform/notifying_async_' . $this->integrationKey, '__return_false');
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = array(
            'category'                => $this->category,
            'disable_global_settings' => 'yes',
            'logo'                    => $this->logo,
            'title'                   => $this->title . ' Integration',
            'is_active'               => $this->isConfigured(),
        );

        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId = null)
    {
        $defaults = array(
            'name'         => __('Event Speech Organizer Feed', 'textdomain'),
            'CustomFields' => (object) array(),
            'conditionals' => array(
                'conditions' => array(),
                'status'     => false,
                'type'       => 'all',
            ),
            'enabled'      => true,
        );

        foreach (array_keys(self::$mappableFields) as $column) {
            $defaults[self::FIELD_PREFIX . $column] = '';
        }

        return $defaults;
    }

    public function getSettingsFields($settings, $formId = null)
    {
        $primaryFields = array();

        foreach (self::$mappableFields as $column => $label) {
            $field = array(
                'key'   => self::FIELD_PREFIX . $column,
                'label' => $label,
            );

            if ('email' === $column) {
                $field['required'] = true;
                // Restricts the picker to the form's email inputs.
                $field['input_options'] = 'emails';
            }

            if ('name' === $column) {
                $field['required'] = true;
            }

            $primaryFields[] = $field;
        }

        return array(
            'fields' => array(
                array(
                    'key'         => 'name',
                    'label'       => __('Feed Name', 'textdomain'),
                    'required'    => true,
                    'placeholder' => __('Your Feed Name', 'textdomain'),
                    'component'   => 'text',
                ),
                array(
                    'key'                => 'CustomFields',
                    'require_list'       => false,
                    'label'              => __('Map Fields', 'textdomain'),
                    'tips'               => __('Map your form fields onto the applicant record. Name and email are required.', 'textdomain'),
                    'component'          => 'map_fields',
                    'field_label_remote' => __('Applicant Field', 'textdomain'),
                    'field_label_local'  => __('Form Field', 'textdomain'),
                    // Fluent Forms spells this key without the second "e".
                    'primary_fileds'     => $primaryFields,
                ),
                array(
                    'require_list' => false,
                    'key'          => 'conditionals',
                    'label'        => __('Conditional Logic', 'textdomain'),
                    'tips'         => __('Create an applicant only when the submission matches these conditions.', 'textdomain'),
                    'component'    => 'conditional_block',
                ),
                array(
                    'require_list'    => false,
                    'key'             => 'enabled',
                    'label'           => __('Status', 'textdomain'),
                    'component'       => 'checkbox-single',
                    'checkbox_label'  => __('Enable This feed', 'textdomain'),
                    'inline_tip'      => __('Submissions whose email already exists as an applicant are skipped.', 'textdomain'),
                ),
            ),
            'button_require_list' => false,
            'integration_title'   => $this->title,
        );
    }

    /**
     * Create the applicant.
     *
     * $feed['processedValues'] holds the feed settings with shortcodes already
     * resolved against the submission, so the mapped values are literal.
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = Arr::get($feed, 'processedValues', array());

        $row = array();

        foreach (array_keys(self::$mappableFields) as $column) {
            $row[$column] = (string) Arr::get($feedData, self::FIELD_PREFIX . $column, '');
        }

        $row['email'] = $this->resolveEmail(Arr::get($row, 'email'), $formData);
        $row['status'] = 'waiting';
        $row['date'] = current_time('mysql');

        if (!$row['name'] || !is_email($row['email'])) {
            do_action(
                'fluentform/integration_action_result',
                $feed,
                'failed',
                __('Event Speech Organizer feed skipped: a valid name and email are required.', 'textdomain')
            );
            return;
        }

        // Reuses the import path, so this shares the CSV/Fluent Forms importer's
        // email de-duplication, validation, truncation and sanitisation.
        $result = (new ApplicantModel())->importRows(array($row), 1);

        if (!empty($result['imported'])) {
            do_action(
                'fluentform/integration_action_result',
                $feed,
                'success',
                __('Applicant has been added to Event Speech Organizer.', 'textdomain')
            );
            return;
        }

        if (!empty($result['duplicates'])) {
            do_action(
                'fluentform/integration_action_result',
                $feed,
                'skipped',
                sprintf(
                    /* translators: %s: email address */
                    __('Skipped: an applicant with the email %s already exists.', 'textdomain'),
                    $row['email']
                )
            );
            return;
        }

        $reason = Arr::get($result, 'issues.0.reason', __('Unknown error.', 'textdomain'));

        do_action(
            'fluentform/integration_action_result',
            $feed,
            'failed',
            sprintf(
                /* translators: %s: failure reason */
                __('Could not add applicant: %s', 'textdomain'),
                $reason
            )
        );
    }

    /**
     * A mapped email normally arrives already resolved. If the feed stored a
     * raw field key instead, fall back to reading it off the submission.
     */
    private function resolveEmail($value, $formData)
    {
        $value = trim((string) $value);

        if (is_email($value)) {
            return $value;
        }

        $fallback = Arr::get($formData, $value);

        return is_string($fallback) ? trim($fallback) : $value;
    }

    // No remote service to authenticate against, so this module is always
    // ready to use.
    public function isConfigured()
    {
        return true;
    }

    // Abstract on the parent, but there are no remote merge fields to fetch.
    public function getMergeFields($list, $listId, $formId)
    {
        return array();
    }

    // Returned unchanged so Fluent Forms does not render an empty global
    // settings page for this module.
    public function addGlobalMenu($setting)
    {
        return $setting;
    }
}
