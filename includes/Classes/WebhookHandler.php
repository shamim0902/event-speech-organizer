<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public webhook endpoint: turns an incoming form-notification POST into a
 * speaker applicant.
 *
 * Built for the Jetpack/Grunion contact-form webhooks that WordCamp sites
 * send, whose JSON keys are slugged field labels prefixed with the form's
 * block id — e.g. `g1155-topictitle`, `g1155-phonenumber`. Keys are matched
 * by keyword after normalisation, so the exact label wording (and the `gNNNN-`
 * prefix) does not have to match anything configured here.
 *
 * URL shape:  POST /wp-json/event-speech-organizer/v1/webhook/{event_id}/{token}
 *
 * The endpoint is necessarily unauthenticated (the sender is a remote site),
 * so the URL carries a site-wide secret token instead. Guessing the URL is
 * the only way in, and a wrong token is rejected before anything is parsed.
 */
class WebhookHandler
{
    const ROUTE_NAMESPACE = 'event-speech-organizer/v1';

    const TOKEN_OPTION = 'event_speech_organizer_webhook_token';

    // Per-event options; the event id is appended.
    const MAPPING_OPTION_PREFIX = 'event_speech_organizer_webhook_mapping_';
    const LAST_PAYLOAD_OPTION_PREFIX = 'event_speech_organizer_webhook_last_payload_';

    // Mapping value meaning "drop this field", as opposed to an absent
    // mapping, which falls back to the keyword rules.
    const MAP_IGNORE = 'ignore';

    // Caps on the captured payload, so a hostile sender cannot bloat the
    // options table through the public endpoint.
    const CAPTURE_MAX_FIELDS = 60;
    const CAPTURE_MAX_VALUE_LENGTH = 500;

    /**
     * Ordered keyword rules mapping a normalised payload key onto an applicant
     * column. First match wins, so the specific rules must precede the generic
     * ones: "isthisyourfirsttimespeakingatawordpressevent" contains
     * "wordpress" and "iftalktypeispanel…cospeakers" contains "talktype", so
     * `question` and `cospeakers` are tested before `username` and `type`.
     */
    private static $keywordRules = array(
        'email'       => array('email'),
        'phone'       => array('phone'),
        'question'    => array('firsttime'),
        'cospeakers'  => array('cospeaker'),
        'social'      => array('social', 'handles'),
        'comment'     => array('bio', 'aboutyou'),
        'topic'       => array('topictitle', 'talktitle', 'sessiontitle'),
        'description' => array('description', 'abstract'),
        'audience'    => array('audience'),
        'type'        => array('typeofsession', 'sessiontype', 'talktype', 'typeoftalk', 'sessionformat'),
        'experience'  => array('experience'),
        'username'    => array('wordpressorg', 'orgprofile', 'username'),
        'name'        => array('name'),
    );

    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route(self::ROUTE_NAMESPACE, '/webhook/(?P<event_id>\d+)/(?P<token>[A-Za-z0-9]+)', array(
                'methods'             => 'POST',
                'callback'            => array(new self(), 'handle'),
                // Auth is the secret token in the URL, checked in handle();
                // remote form plugins cannot send a WordPress nonce or cookie.
                'permission_callback' => '__return_true',
            ));
        });
    }

    /**
     * The shared secret embedded in every webhook URL. Generated once and
     * persisted, so existing URLs keep working across requests.
     */
    public static function getToken()
    {
        $token = get_option(self::TOKEN_OPTION);

        if (!is_string($token) || strlen($token) < 32) {
            $token = wp_generate_password(40, false, false);
            update_option(self::TOKEN_OPTION, $token, false);
        }

        return $token;
    }

    /**
     * Replace the secret with a fresh one. Every previously shared webhook
     * URL stops working the moment this runs.
     */
    public static function regenerateToken()
    {
        $token = wp_generate_password(40, false, false);
        update_option(self::TOKEN_OPTION, $token, false);

        return $token;
    }

    /**
     * The webhook URL to paste into the sending site, scoped to one event.
     */
    public static function getUrl($eventId)
    {
        return rest_url(self::ROUTE_NAMESPACE . '/webhook/' . (int) $eventId . '/' . self::getToken());
    }

    /**
     * The event's manual field mapping: incoming payload key => applicant
     * column (or MAP_IGNORE). Keys without an entry fall back to the keyword
     * rules.
     */
    public static function getMapping($eventId)
    {
        $mapping = get_option(self::MAPPING_OPTION_PREFIX . (int) $eventId, array());

        return is_array($mapping) ? $mapping : array();
    }

    /**
     * Validate and persist an event's manual mapping. Unknown columns are
     * dropped rather than stored, so a stale or hand-crafted save can never
     * write to a column the importer does not accept.
     */
    public static function saveMapping($eventId, $mapping)
    {
        $columns = ApplicantModel::getMappableColumns();
        $valid = array();

        foreach ((array) $mapping as $key => $column) {
            $key = sanitize_text_field((string) $key);
            $column = sanitize_text_field((string) $column);

            if ('' === $key || '' === $column) {
                continue;
            }

            if (self::MAP_IGNORE !== $column && !isset($columns[$column])) {
                continue;
            }

            $valid[$key] = $column;
        }

        update_option(self::MAPPING_OPTION_PREFIX . (int) $eventId, $valid, false);

        return $valid;
    }

    /**
     * The most recent payload the event's webhook received:
     * array('received_at' => mysql datetime, 'fields' => key => value).
     */
    public static function getLastPayload($eventId)
    {
        $payload = get_option(self::LAST_PAYLOAD_OPTION_PREFIX . (int) $eventId, array());

        return is_array($payload) ? $payload : array();
    }

    /**
     * Remove an event's webhook options. Called when the event is deleted.
     */
    public static function deleteEventData($eventId)
    {
        delete_option(self::MAPPING_OPTION_PREFIX . (int) $eventId);
        delete_option(self::LAST_PAYLOAD_OPTION_PREFIX . (int) $eventId);
    }

    /**
     * The column the keyword rules would pick for one raw payload key, or
     * null. Lets the mapper UI show what "Auto" resolves to.
     */
    public function autoColumnFor($rawKey)
    {
        return $this->resolveColumn($this->normalizeKey($rawKey));
    }

    public function handle(\WP_REST_Request $request)
    {
        if (!hash_equals(self::getToken(), (string) $request['token'])) {
            return new \WP_REST_Response(array(
                'status'  => false,
                'message' => 'Invalid webhook token.',
            ), 401);
        }

        $eventId = (int) $request['event_id'];

        if (!$eventId || !(new EventModel())->exists($eventId)) {
            return new \WP_REST_Response(array(
                'status'  => false,
                'message' => 'Unknown event.',
            ), 404);
        }

        $payload = $this->extractPayload($request);

        if (!$payload) {
            return new \WP_REST_Response(array(
                'status'  => false,
                'message' => 'Empty or unreadable payload. Send the form fields as JSON or form-encoded POST data.',
            ), 400);
        }

        // Captured before any validation, so the field mapper can show the
        // incoming keys even when the submission itself is rejected.
        $this->capturePayload($eventId, $payload);

        $row = $this->mapPayload($payload, self::getMapping($eventId));

        if (empty($row['name']) || empty($row['email']) || !is_email($row['email'])) {
            return new \WP_REST_Response(array(
                'status'  => false,
                'message' => 'Could not find a name and a valid email in the payload.',
            ), 422);
        }

        $row['status'] = 'waiting';
        $row['date'] = current_time('mysql');

        // Shares the importer's per-event email dedupe, validation, column
        // truncation and sanitisation instead of reimplementing them.
        $result = (new ApplicantModel())->importRows(array($row), 1, $eventId);

        if (!empty($result['imported'])) {
            return new \WP_REST_Response(array(
                'status'  => true,
                'message' => 'Applicant created.',
            ), 200);
        }

        // A repeat submission is not an error the sender should retry, so it
        // still answers 200.
        if (!empty($result['duplicates'])) {
            return new \WP_REST_Response(array(
                'status'  => true,
                'message' => 'Skipped: an applicant with this email already exists for this event.',
            ), 200);
        }

        $reason = isset($result['issues'][0]['reason']) ? $result['issues'][0]['reason'] : 'Unknown error.';

        return new \WP_REST_Response(array(
            'status'  => false,
            'message' => 'Could not create the applicant: ' . $reason,
        ), 500);
    }

    /**
     * The form fields from the request body. JSON is the normal case; falls
     * back to form-encoded params so other webhook senders also work.
     */
    private function extractPayload(\WP_REST_Request $request)
    {
        $payload = $request->get_json_params();

        if (!is_array($payload) || !$payload) {
            $payload = $request->get_body_params();
        }

        // Some senders wrap the answers in a `fields` envelope.
        if (isset($payload['fields']) && is_array($payload['fields'])) {
            $payload = $payload['fields'];
        }

        return is_array($payload) ? $payload : array();
    }

    /**
     * Remember the payload so the field mapper can offer the event's real
     * incoming keys. Field count and value length are capped — this endpoint
     * is public.
     */
    private function capturePayload($eventId, array $payload)
    {
        $fields = array();

        foreach ($payload as $key => $value) {
            if (count($fields) >= self::CAPTURE_MAX_FIELDS) {
                break;
            }

            $key = sanitize_text_field((string) $key);

            if ('' === $key) {
                continue;
            }

            $value = sanitize_textarea_field($this->flattenValue($value));

            $fields[$key] = function_exists('mb_substr')
                ? mb_substr($value, 0, self::CAPTURE_MAX_VALUE_LENGTH)
                : substr($value, 0, self::CAPTURE_MAX_VALUE_LENGTH);
        }

        update_option(self::LAST_PAYLOAD_OPTION_PREFIX . (int) $eventId, array(
            'received_at' => current_time('mysql'),
            'fields'      => $fields,
        ), false);
    }

    /**
     * Build an ApplicantModel row from the payload. The event's manual
     * mapping is applied first — an explicit choice must beat a keyword
     * guess even when the guessed key appears earlier in the payload — then
     * unmapped keys go through the keyword rules. Either way a column keeps
     * its first non-empty value.
     */
    private function mapPayload(array $payload, array $mapping)
    {
        $row = array();

        foreach ($payload as $key => $value) {
            $key = sanitize_text_field((string) $key);

            if (!array_key_exists($key, $mapping)) {
                continue;
            }

            $column = $mapping[$key];

            if (self::MAP_IGNORE === $column || isset($row[$column])) {
                continue;
            }

            $value = $this->flattenValue($value);

            if ('' !== $value) {
                $row[$column] = $value;
            }
        }

        foreach ($payload as $key => $value) {
            $key = sanitize_text_field((string) $key);

            // Mapped keys — including ignored ones — are already handled.
            if (array_key_exists($key, $mapping)) {
                continue;
            }

            $column = $this->resolveColumn($this->normalizeKey($key));

            if (!$column || isset($row[$column])) {
                continue;
            }

            $value = $this->flattenValue($value);

            if ('' !== $value) {
                $row[$column] = $value;
            }
        }

        return $row;
    }

    /**
     * `g1155-wherecanwefindyousocialhandles044website044etc` →
     * `wherecanwefindyousocialhandleswebsiteetc`. The block-id prefix,
     * punctuation escapes (044 is an escaped comma) and separators all
     * disappear, leaving only lowercase letters for the keyword match.
     */
    private function normalizeKey($key)
    {
        $key = strtolower((string) $key);
        $key = preg_replace('/^g\d+-/', '', $key);

        return preg_replace('/[^a-z]/', '', $key);
    }

    private function resolveColumn($normalizedKey)
    {
        if ('' === $normalizedKey) {
            return null;
        }

        foreach (self::$keywordRules as $column => $keywords) {
            foreach ($keywords as $keyword) {
                if (false !== strpos($normalizedKey, $keyword)) {
                    return $column;
                }
            }
        }

        return null;
    }

    /**
     * Checkbox groups arrive as arrays; the applicant columns are flat text.
     */
    private function flattenValue($value)
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $value)));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
