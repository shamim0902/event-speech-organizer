<?php

namespace EventSpeechOrganizer\Classes;

use EventSpeechOrganizer\Classes\ApplicantModel;

class Menu
{
    public function register()
    {
        add_action('admin_menu', array($this, 'addMenus'));
    }

    public function addMenus()
    {
        $menuPermission = AccessControl::hasTopLevelMenuPermission();
        if (!$menuPermission) {
            return;
        }

        $title = __('Events', 'textdomain');
        global $submenu;
        $hook = add_menu_page(
            $title,
            $title,
            $menuPermission,
            'event_speech_organizer.php',
            array($this, 'enqueueAssets'),
            'dashicons-admin-site',
            25
        );

        add_action('load-' . $hook, array($this, 'prepareScreen'));

        // Hash links into the Vue app. An event's applicants and slots are
        // reached through the events screen, not through the admin menu.
        $submenu['event_speech_organizer.php']['events'] = array(
            __('My events', 'textdomain'),
            $menuPermission,
            'admin.php?page=event_speech_organizer.php#/',
        );
        $submenu['event_speech_organizer.php']['settings'] = array(
            __('Settings', 'textdomain'),
            $menuPermission,
            'admin.php?page=event_speech_organizer.php#/settings',
        );
    }

    /**
     * This screen is a self-contained Vue app that owns the whole viewport, so
     * nothing else should render above it inside #wpbody-content — no third
     * party admin notices, no screen-meta panel.
     *
     * Runs on load-{$hook}, which fires after admin_init (so other plugins have
     * already registered their notices) but before admin-header.php prints them.
     * Scoped to this page only; every other admin screen is untouched.
     */
    public function prepareScreen()
    {
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');

        // Drops the "Screen Options" / "Help" tabs, leaving #screen-meta empty.
        add_filter('screen_options_show_screen', '__return_false');

        $screen = get_current_screen();
        if ($screen) {
            $screen->remove_help_tabs();
        }
    }

    /**
     * Cache-busting version for one asset. The file's mtime is appended so a
     * redeploy always changes the ?ver= query string — a bare plugin version
     * left browsers serving stale cached bundles after deploys that did not
     * bump it.
     */
    private function assetVersion($relativePath)
    {
        $file = EVENT_SPEECH_ORGANIZER_DIR . 'assets/' . $relativePath;
        $mtime = file_exists($file) ? filemtime($file) : false;

        return $mtime
            ? EVENT_SPEECH_ORGANIZER_VERSION . '.' . $mtime
            : EVENT_SPEECH_ORGANIZER_VERSION;
    }

    public function enqueueAssets()
    {
        do_action('event_speech_organizer/render_admin_app');

        wp_enqueue_script(
            'event_speech_organizer_boot',
            EVENT_SPEECH_ORGANIZER_URL . 'assets/js/boot.js',
            array('jquery'),
            $this->assetVersion('js/boot.js'),
            true
        );

        // 3rd party developers can now add their scripts here
        do_action('event_speech_organizer/booting_admin_app');
        wp_enqueue_script(
            'event-speech-organizer_js',
            EVENT_SPEECH_ORGANIZER_URL . 'assets/js/plugin-main-js-file.js',
            array('event_speech_organizer_boot'),
            $this->assetVersion('js/plugin-main-js-file.js'),
            true
        );

        //enque css file
        wp_enqueue_style(
            'event-speech-organizer_admin_css',
            EVENT_SPEECH_ORGANIZER_URL . 'assets/css/element.css',
            array(),
            $this->assetVersion('css/element.css')
        );

        $eventSpeechOrganizer = apply_filters('event_speech_organizer/admin_app_vars', array(
            //'image_upload_url' => admin_url('admin-ajax.php?action=wpf_global_settings_handler&route=wpf_upload_image'),
            'assets_url' => EVENT_SPEECH_ORGANIZER_URL . 'assets/',
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('event_speech_organizer_admin'),
            'import_columns' => ApplicantModel::getImportableColumns(),
            'has_fluentform' => FluentFormImporter::isAvailable(),
        ));

        wp_localize_script('event_speech_organizer_boot', 'eventSpeechOrganizerAdmin', $eventSpeechOrganizer);
    }
}
