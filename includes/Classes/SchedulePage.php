<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public, printable schedule for an event.
 *
 * Renders a standalone page (no theme involved) at
 * `home_url('/?eso_schedule={event_id}&eso_key={token}')`. The page is the
 * shareable artefact: send the link to speakers and attendees, or open it and
 * use the browser's Print → Save as PDF for a file.
 *
 * Access is either a per-event secret token (only while sharing is switched
 * on) or an admin session, so organisers can always preview an unpublished
 * schedule.
 */
class SchedulePage
{
    const QUERY_VAR = 'eso_schedule';

    const KEY_VAR = 'eso_key';

    const TOKEN_PREFIX = 'event_speech_organizer_schedule_token_';

    const PUBLIC_PREFIX = 'event_speech_organizer_schedule_public_';

    /**
     * Talk types, mirroring the slot dialog's list in Slots.vue. Kept here so
     * the public page can label and colour a slot without the admin bundle.
     */
    private static $types = array(
        'panel'        => array('label' => 'Panel', 'color' => '#7c4dff'),
        'keynote'      => array('label' => 'Keynote', 'color' => '#e5484d'),
        'semi-keynote' => array('label' => 'Semi Keynote', 'color' => '#f5a524'),
        'lightning'    => array('label' => 'Lightning Talk', 'color' => '#2f80ed'),
        'break'        => array('label' => 'Break', 'color' => '#8b8b8b'),
    );

    public static function register()
    {
        add_action('template_redirect', array(__CLASS__, 'maybeRender'));
    }

    public static function maybeRender()
    {
        $eventId = isset($_GET[self::QUERY_VAR]) ? (int) $_GET[self::QUERY_VAR] : 0;

        if (!$eventId) {
            return;
        }

        $event = (new EventModel())->find($eventId);

        if (!$event) {
            self::deny(__('This schedule is not available.', 'textdomain'));
        }

        $key = isset($_GET[self::KEY_VAR]) ? sanitize_text_field(wp_unslash($_GET[self::KEY_VAR])) : '';

        if (!self::canView($eventId, $key)) {
            self::deny(__('This schedule link is not available.', 'textdomain'));
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');

        self::render($event);
        exit;
    }

    /**
     * Organisers may always preview. Everyone else needs sharing switched on
     * plus the matching token.
     */
    private static function canView($eventId, $key)
    {
        if (AccessControl::hasTopLevelMenuPermission()) {
            return true;
        }

        if (!self::isPublic($eventId)) {
            return false;
        }

        return $key && hash_equals(self::getToken($eventId), $key);
    }

    private static function deny($message)
    {
        wp_die(
            esc_html($message),
            esc_html__('Schedule unavailable', 'textdomain'),
            array('response' => 403)
        );
    }

    // Sharing state -------------------------------------------------------

    public static function isPublic($eventId)
    {
        return (bool) get_option(self::PUBLIC_PREFIX . (int) $eventId, false);
    }

    public static function setPublic($eventId, $isPublic)
    {
        update_option(self::PUBLIC_PREFIX . (int) $eventId, $isPublic ? 1 : 0, false);

        if ($isPublic) {
            // Make sure a token exists the moment sharing goes live.
            self::getToken($eventId);
        }
    }

    public static function getToken($eventId)
    {
        $option = self::TOKEN_PREFIX . (int) $eventId;
        $token = get_option($option);

        if (!$token) {
            $token = self::regenerateToken($eventId);
        }

        return $token;
    }

    public static function regenerateToken($eventId)
    {
        $token = wp_generate_password(32, false, false);
        update_option(self::TOKEN_PREFIX . (int) $eventId, $token, false);

        return $token;
    }

    public static function getUrl($eventId)
    {
        return add_query_arg(
            array(
                self::QUERY_VAR => (int) $eventId,
                self::KEY_VAR   => self::getToken($eventId),
            ),
            home_url('/')
        );
    }

    public static function deleteEventData($eventId)
    {
        delete_option(self::TOKEN_PREFIX . (int) $eventId);
        delete_option(self::PUBLIC_PREFIX . (int) $eventId);
    }

    // Rendering -----------------------------------------------------------

    /**
     * Slots in agenda order, each with its speakers resolved to applicant
     * records. Speaker entries are applicant ids, but free-typed names have
     * always been allowed too — those fall back to the raw string.
     */
    private static function agenda($eventId)
    {
        $slots = (new SpeakerSlots())->get($eventId);
        $slots = isset($slots['data']) ? $slots['data'] : array();

        $applicants = array();
        foreach ((array) (new ApplicantModel())->getAll($eventId) as $applicant) {
            $applicants[(string) $applicant->id] = $applicant;
        }

        $rows = array();

        foreach ($slots as $slot) {
            $speakers = array();

            foreach ((array) $slot->speakers as $speaker) {
                $key = (string) $speaker;

                if (isset($applicants[$key])) {
                    $speakers[] = array(
                        'name'  => $applicants[$key]->name,
                        'email' => $applicants[$key]->email,
                        'topic' => $applicants[$key]->topic,
                    );
                } elseif ($key !== '') {
                    $speakers[] = array('name' => $key, 'email' => '', 'topic' => '');
                }
            }

            $rows[] = array(
                'from'     => $slot->from,
                'to'       => $slot->to,
                'name'     => $slot->name,
                'type'     => $slot->talk_type,
                'speakers' => $speakers,
            );
        }

        // Start time sorts as text because it is zero padded HH:MM; untimed
        // slots sink to the bottom.
        usort($rows, function ($a, $b) {
            $fromA = $a['from'] ? $a['from'] : '99:99';
            $fromB = $b['from'] ? $b['from'] : '99:99';

            if ($fromA === $fromB) {
                return 0;
            }

            return $fromA < $fromB ? -1 : 1;
        });

        return $rows;
    }

    private static function typeLabel($type)
    {
        return isset(self::$types[$type]) ? self::$types[$type]['label'] : $type;
    }

    private static function typeColor($type)
    {
        return isset(self::$types[$type]) ? self::$types[$type]['color'] : '#64748b';
    }

    private static function avatar($email, $size = 64)
    {
        return 'https://secure.gravatar.com/avatar/' . md5(strtolower(trim((string) $email)))
            . '?size=' . (int) $size . '&default=mm&rating=g';
    }

    private static function render($event)
    {
        $rows = self::agenda($event->id);
        $isPreview = !self::isPublic($event->id);

        $talks = 0;
        foreach ($rows as $row) {
            if ($row['type'] !== 'break') {
                $talks++;
            }
        }

        include EVENT_SPEECH_ORGANIZER_DIR . 'includes/Views/schedule.php';
    }

}
