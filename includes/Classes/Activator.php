<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax Handler Class
 * @since 1.0.0
 */
class Activator
{
    public function migrateDatabases($network_wide = false)
    {
        global $wpdb;
        if ($network_wide) {
            // Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
            if (function_exists('get_sites') && function_exists('get_current_network_id')) {
                $site_ids = get_sites(array('fields' => 'ids', 'network_id' => get_current_network_id()));
            } else {
                $site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;");
            }
            // Install the plugin for all these sites.
            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                $this->migrate();
                restore_current_blog();
            }
        } else {
            $this->migrate();
        }
    }

    /**
     * Bumped whenever the schema changes so existing installs upgrade.
     * @see Activator::maybeUpgrade()
     */
    const DB_VERSION = '2';

    const DB_VERSION_OPTION = 'event_speech_organizer_db_version';

    /**
     * Runs on every load. Cheap when the schema is current: one option read.
     */
    public static function maybeUpgrade()
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        (new self())->migrateDatabases(false);
    }

    private function migrate()
    {
        $this->createApplicantTable();
        $this->slotTable();
        $this->createEventTable();

        // Applicants and slots used to be global. Scope them to an event.
        $this->addColumnIfMissing($GLOBALS['wpdb']->prefix . 'speakers', 'event_id');
        $this->addColumnIfMissing($GLOBALS['wpdb']->prefix . 'speakers_slots', 'event_id');

        $this->seedDefaultEvent();

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public function createEventTable()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'speaker_events';

        $sql = "CREATE TABLE $table_name (
            `id` int NOT NULL AUTO_INCREMENT,
            `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `event_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `status` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `created_at` datetime NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $this->runSQL($sql, $table_name);
    }

    /**
     * dbDelta is not used for this: runSQL() only fires on table creation, and
     * dbDelta is unreliable against the slots table because `from` and `to`
     * are reserved words. An explicit guarded ALTER is predictable.
     */
    private function addColumnIfMissing($tableName, $column)
    {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            return;
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM `$tableName` LIKE %s", $column)
        );

        if ($exists) {
            return;
        }

        $wpdb->query("ALTER TABLE `$tableName` ADD `$column` INT NOT NULL DEFAULT 0");
        $wpdb->query("ALTER TABLE `$tableName` ADD INDEX `$column` (`$column`)");
    }

    /**
     * Existing applicants and slots predate events. Rather than leaving them
     * orphaned — and therefore invisible in an event-scoped UI — park them
     * under one default event.
     */
    private function seedDefaultEvent()
    {
        global $wpdb;

        $eventsTable = $wpdb->prefix . 'speaker_events';
        $speakersTable = $wpdb->prefix . 'speakers';
        $slotsTable = $wpdb->prefix . 'speakers_slots';

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $eventsTable") > 0) {
            return;
        }

        $orphanedSpeakers = (int) $wpdb->get_var("SELECT COUNT(*) FROM $speakersTable WHERE event_id = 0");
        $orphanedSlots = (int) $wpdb->get_var("SELECT COUNT(*) FROM $slotsTable WHERE event_id = 0");

        // A fresh install has nothing to rescue; let the user create their own.
        if (!$orphanedSpeakers && !$orphanedSlots) {
            return;
        }

        $wpdb->insert($eventsTable, array(
            'title'       => __('Default Event', 'textdomain'),
            'description' => __('Applicants and slots that existed before events were introduced.', 'textdomain'),
            'location'    => '',
            'event_date'  => '',
            'status'      => 'active',
            'created_at'  => current_time('mysql'),
        ));

        $eventId = (int) $wpdb->insert_id;

        if (!$eventId) {
            return;
        }

        $wpdb->query($wpdb->prepare("UPDATE $speakersTable SET event_id = %d WHERE event_id = 0", $eventId));
        $wpdb->query($wpdb->prepare("UPDATE $slotsTable SET event_id = %d WHERE event_id = 0", $eventId));
    }

    public function slotTable()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'speakers_slots';

        $sql = "CREATE TABLE $table_name (
            `id` int NOT NULL AUTO_INCREMENT,
            `talk_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `speakers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $this->runSQL($sql, $table_name);
    }

    public function createApplicantTable()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'speakers';

        $sql = "CREATE TABLE $table_name (
            `id` int NOT NULL AUTO_INCREMENT,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `phone` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `username` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `social` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `topic` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `cospeakers` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `audience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `experience` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `question` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `consent` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `ip` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `status` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $this->runSQL($sql, $table_name);
    }

    private function runSQL($sql, $tableName)
    {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
