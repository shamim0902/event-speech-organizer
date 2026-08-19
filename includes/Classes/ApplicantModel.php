<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}


class ApplicantModel
{
    /**
     * Importable columns and their varchar limits. longtext columns are listed
     * with a null limit — they need no truncation.
     */
    private static $importableColumns = array(
        'name'        => 100,
        'email'       => 60,
        'comment'     => null,
        'phone'       => 60,
        'username'    => 1000,
        'social'      => 2000,
        'type'        => 200,
        'topic'       => 2000,
        'description' => null,
        'cospeakers'  => 1000,
        'audience'    => null,
        'experience'  => 2000,
        'question'    => 2000,
        'status'      => 60,
        'date'        => 100,
    );

    private static $allowedStatuses = array('approved', 'rejected', 'waiting');

    public static function getImportableColumns()
    {
        return array_keys(self::$importableColumns);
    }

    /**
     * Columns an external source (webhook, form feed) may map onto, with
     * their admin-facing labels. `status`, `date`, `consent` and `ip` are
     * deliberately absent — those are set by the plugin, not the sender.
     */
    public static function getMappableColumns()
    {
        return array(
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
    }

    /**
     * Bulk-insert applicants from a parsed CSV, skipping any row whose email
     * already exists in the table or repeats earlier in the same batch.
     *
     * @param array $rows          list of column => value maps
     * @param int   $rowNumberBase number reported for the first row. CSV passes
     *                             2 (header occupies row 1); Fluent Forms passes
     *                             the 1-based position of the slice.
     * @return array summary counts plus per-row skip reasons
     */
    public function importRows($rows, $rowNumberBase = 2, $eventId = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $eventId = (int) $eventId;

        // Every applicant belongs to an event; without one the rows would be
        // written where no screen can ever show them.
        if (!$eventId || !(new EventModel())->exists($eventId)) {
            return array(
                'status'     => false,
                'message'    => __('A valid event is required to import applicants.', 'textdomain'),
                'imported'   => 0,
                'duplicates' => 0,
                'invalid'    => 0,
                'failed'     => 0,
                'issues'     => array(),
            );
        }

        $existing = array_flip($this->getExistingEmails($eventId));

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;
        $failed = 0;
        $issues = array();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + $rowNumberBase;

            if (!is_array($row)) {
                $invalid++;
                $issues[] = array('row' => $rowNumber, 'reason' => 'Malformed row.');
                continue;
            }

            $name = isset($row['name']) ? trim($row['name']) : '';
            $email = isset($row['email']) ? strtolower(trim($row['email'])) : '';

            if (!$name || !$email) {
                $invalid++;
                $issues[] = array('row' => $rowNumber, 'reason' => 'Missing name or email.');
                continue;
            }

            if (!is_email($email)) {
                $invalid++;
                $issues[] = array('row' => $rowNumber, 'reason' => 'Invalid email: ' . $email);
                continue;
            }

            if (isset($existing[$email])) {
                $duplicates++;
                $issues[] = array('row' => $rowNumber, 'reason' => 'Duplicate email: ' . $email);
                continue;
            }

            $data = $this->prepareImportRow($row, $name, $email);
            $data['event_id'] = $eventId;

            $inserted = $wpdb->insert($table_name, $data);

            if ($inserted === false) {
                $failed++;
                $issues[] = array(
                    'row'    => $rowNumber,
                    'reason' => $wpdb->last_error ? $wpdb->last_error : 'Database insert failed.',
                );
                continue;
            }

            // Claim the email so later rows in this same batch dedupe against it.
            $existing[$email] = true;
            $imported++;
        }

        return array(
            'status'     => true,
            'imported'   => $imported,
            'duplicates' => $duplicates,
            'invalid'    => $invalid,
            'failed'     => $failed,
            // Cap the detail list so a bad 5,000-row file cannot balloon the response.
            'issues'     => array_slice($issues, 0, 50),
        );
    }

    private function prepareImportRow($row, $name, $email)
    {
        $data = array();

        foreach (self::$importableColumns as $column => $maxLength) {
            $value = isset($row[$column]) ? $row[$column] : '';
            $value = is_scalar($value) ? (string) $value : '';

            // The longtext columns hold prose, so keep their line breaks —
            // sanitize_text_field() would flatten a multi-paragraph bio.
            if ($maxLength === null) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }

            if ($maxLength && function_exists('mb_substr')) {
                $value = mb_substr($value, 0, $maxLength);
            } elseif ($maxLength) {
                $value = substr($value, 0, $maxLength);
            }

            $data[$column] = $value;
        }

        $data['name'] = sanitize_text_field($name);
        $data['email'] = sanitize_email($email);

        $status = strtolower(trim($data['status']));
        $data['status'] = in_array($status, self::$allowedStatuses, true) ? $status : 'waiting';

        // Columns that exist on the table but are never supplied by an import.
        $data['consent'] = '';
        $data['ip'] = '';

        return $data;
    }

    /**
     * Emails already used within an event. De-duplication is per event, not
     * global: the same speaker may legitimately apply to two different events.
     */
    public function getExistingEmails($eventId = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $emails = $wpdb->get_col($wpdb->prepare(
            "SELECT email FROM $table_name WHERE email <> '' AND event_id = %d",
            (int) $eventId
        ));

        return array_map(function ($email) {
            return strtolower(trim($email));
        }, (array) $emails);
    }

    public function getAll($eventId = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE event_id = %d ORDER BY id DESC",
            (int) $eventId
        ));
    }

    public function get($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $eventId = isset($request['event_id']) ? (int) $request['event_id'] : 0;
        $options = isset($request['options']) ? (array) $request['options'] : array();

        $where = array('event_id = %d');
        $params = array($eventId);

        if (!empty($options['status'])) {
            $statuses = array_map('sanitize_text_field', (array) $options['status']);
            $where[] = 'status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
            $params = array_merge($params, $statuses);
        }

        if (!empty($options['not_status'])) {
            $statuses = array_map('sanitize_text_field', (array) $options['not_status']);
            $where[] = 'status NOT IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
            $params = array_merge($params, $statuses);
        }

        $sql = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC';

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        return array(
            'data' => $results ? $results : array()
        );
    }

    public function searchBy($searchQuery, $eventId = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $like = '%' . $wpdb->esc_like((string) $searchQuery) . '%';

        $columns = array(
            'name', 'email', 'phone', 'username', 'social', 'type', 'topic',
            'description', 'cospeakers', 'audience', 'experience', 'question',
        );

        $conditions = array();
        $params = array((int) $eventId);

        foreach ($columns as $column) {
            $conditions[] = "$column LIKE %s";
            $params[] = $like;
        }

        $sql = "SELECT * FROM $table_name
                WHERE event_id = %d AND (" . implode(' OR ', $conditions) . ')
                LIMIT 10';

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        $suggestion = array();
        foreach ($results as $key => $value) {
            $suggestion[] = array(
                'label' => $value->topic,
                'value' => $value->topic
            );
        }

        return $suggestion;
    }

    public function updateStatus($query)
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $data = array(
            'status' => sanitize_text_field($query['status'])
        );
        $where = array(
            'id' => $query['id']
        );

        $wpdb->update($table_name, $data, $where);
    }

    public function update($speaker)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $id = isset($speaker['id']) ? (int) $speaker['id'] : 0;

        if (!$id) {
            return;
        }

        $wpdb->update($table_name, $this->sanitizeApplicant($speaker), array('id' => $id));
    }

    /**
     * Delete one applicant, scoped to its event so a stray id from another
     * event's screen can never remove the wrong row.
     */
    public function delete($id, $eventId)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $id = (int) $id;
        $eventId = (int) $eventId;

        if (!$id || !$eventId) {
            return array('status' => false, 'message' => __('No applicant to delete.', 'textdomain'));
        }

        $deleted = $wpdb->delete($table_name, array('id' => $id, 'event_id' => $eventId));

        if (!$deleted) {
            return array('status' => false, 'message' => __('Applicant not found.', 'textdomain'));
        }

        return array('status' => true);
    }

    public function insert($speaker)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $data = $this->sanitizeApplicant($speaker);
        $data['event_id'] = isset($speaker['event_id']) ? (int) $speaker['event_id'] : 0;

        $inserted = $wpdb->insert($table_name, $data);

        if ($inserted === false) {
            return array('status' => false, 'message' => $wpdb->last_error);
        }

        return array('status' => true, 'id' => (int) $wpdb->insert_id);
    }

    /**
     * Shared column sanitiser. Keys are read defensively because add/edit
     * payloads do not always carry every column.
     */
    private function sanitizeApplicant($speaker)
    {
        $data = array();

        foreach (self::$importableColumns as $column => $maxLength) {
            $value = isset($speaker[$column]) ? $speaker[$column] : '';
            $value = is_scalar($value) ? (string) $value : '';

            $data[$column] = ($maxLength === null)
                ? sanitize_textarea_field($value)
                : sanitize_text_field($value);
        }

        foreach (array('question', 'consent', 'ip') as $column) {
            if (!isset($data[$column])) {
                $data[$column] = isset($speaker[$column]) ? sanitize_text_field((string) $speaker[$column]) : '';
            }
        }

        return $data;
    }
}
