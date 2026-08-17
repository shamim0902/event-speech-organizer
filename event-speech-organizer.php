<?php

/*
Plugin Name: Event Speech Organizer
Plugin URI: #
Description: A WordPress plugin to manage event eventSpeechOrganizer and schedule event speech
Version: 1.0.0
Author: #
Author URI: #
License: A "Slug" license name e.g. GPL2
Text Domain: event_speech_organizer
*/


/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 *
 * Copyright 2019 Plugin Name LLC. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!defined('EVENT_SPEECH_ORGANIZER_VERSION')) {
    define('EVENT_SPEECH_ORGANIZER_VERSION_LITE', true);
    define('EVENT_SPEECH_ORGANIZER_VERSION', '1.1.0');
    define('EVENT_SPEECH_ORGANIZER_MAIN_FILE', __FILE__);
    define('EVENT_SPEECH_ORGANIZER_URL', plugin_dir_url(__FILE__));
    define('EVENT_SPEECH_ORGANIZER_DIR', plugin_dir_path(__FILE__));
    define('EVENT_SPEECH_ORGANIZER_UPLOAD_DIR', '/eventSpeechOrganizer');

    class EventSpeechOrganizer
    {
        public function boot()
        {
            require_once EVENT_SPEECH_ORGANIZER_DIR . 'includes/autoload.php';

            // Activation only fires on a fresh install, so schema changes for
            // existing installs need their own upgrade path.
            \EventSpeechOrganizer\Classes\Activator::maybeUpgrade();

            if (is_admin()) {
                $this->adminHooks();
            }

            // Must run outside the is_admin() guard: form submissions are
            // processed on the front end, so the integration would never fire.
            $this->integrationHooks();
        }

        /**
         * Fluent Forms integration. Registers a per-form feed that creates an
         * applicant from a submission.
         */
        public function integrationHooks()
        {
            // The integration needs no API credentials, so there is nothing to
            // configure globally and nothing to switch on — keep it always
            // available rather than hiding it behind a global toggle.
            add_filter('fluentform/is_integration_enabled_EventSpeechOrganizer', '__return_true');

            add_action('fluentform/loaded', function ($app) {
                new \EventSpeechOrganizer\Classes\Integrations\FluentFormIntegration($app);
            });
        }

        public function adminHooks()
        {
            //Register Admin menu
            $menu = new \EventSpeechOrganizer\Classes\Menu();
            $menu->register();

            // Top Level Ajax Handlers
            $ajaxHandler = new \EventSpeechOrganizer\Classes\AdminAjaxHandler();
            $ajaxHandler->registerEndpoints();

            add_action('event_speech_organizer/render_admin_app', function () {
                $adminApp = new \EventSpeechOrganizer\Classes\AdminApp();
                $adminApp->bootView();
            });
        }

        public function textDomain()
        {
            load_plugin_textdomain('event-speech-organizer', false, basename(dirname(__FILE__)) . '/languages');
        }
    }

    add_action('plugins_loaded', function () {
        (new EventSpeechOrganizer())->boot();
    });

    register_activation_hook(__FILE__, function ($newWorkWide) {
        require_once(EVENT_SPEECH_ORGANIZER_DIR . 'includes/Classes/Activator.php');
        $activator = new \EventSpeechOrganizer\Classes\Activator();
        $activator->migrateDatabases($newWorkWide);
    });

    // disabled admin-notice on dashboard
    add_action('admin_init', function () {
        $disablePages = [
            'eventSpeechOrganizer.php',
        ];
        if (isset($_GET['page']) && in_array($_GET['page'], $disablePages)) {
            remove_all_actions('admin_notices');
        }
    });
} else {
    add_action('admin_init', function () {
        deactivate_plugins(plugin_basename(__FILE__));
    });
}
