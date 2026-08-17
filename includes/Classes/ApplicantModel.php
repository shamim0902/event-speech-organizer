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
     * Bulk-insert applicants from a parsed CSV, skipping any row whose email
     * already exists in the table or repeats earlier in the same batch.
     *
     * @param array $rows list of column => value maps
     * @return array summary counts plus per-row skip reasons
     */
    public function importRows($rows)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $existing = array_flip($this->getExistingEmails());

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;
        $failed = 0;
        $issues = array();

        foreach ($rows as $index => $row) {
            // Row numbers are 1-based and skip the header, so the number here
            // matches what the user sees in a spreadsheet.
            $rowNumber = $index + 2;

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

    public function getExistingEmails()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';

        $emails = $wpdb->get_col("SELECT email FROM $table_name WHERE email <> ''");

        return array_map(function ($email) {
            return strtolower(trim($email));
        }, (array) $emails);
    }

    public function getAll()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';
        $sql = "SELECT * FROM $table_name";
        return $wpdb->get_results($sql);
    }

    public function get($request)
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';
        $sql = "SELECT * FROM $table_name";

        $options  = isset($_REQUEST['options']) ? $_REQUEST['options'] : array();

        if (isset($options['not_status'])) {
            $exc = '';
            foreach ($options['not_status'] as $value) {
                $exc .= "'" . sanitize_text_field($value) . "',";
            }
            $exc = rtrim($exc, ",");
            $sql .= " WHERE status NOT IN ($exc)";
        }

        if (isset($options['status'])) {
            $List = implode(', ', $options['status']);
            $sql .= " WHERE status IN ('" . $List . "' )";
        }

        $sql = rtrim($sql, "AND");

        $results = $wpdb->get_results($sql);

        return array(
            'data' => $results
        );
    }

    public function searchBy($searchQuery)
    {
        //search from data using $searchQuery
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';
        $sql = "SELECT * FROM $table_name WHERE name LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%' OR phone LIKE '%$searchQuery%' OR username LIKE '%$searchQuery%' OR social LIKE '%$searchQuery%' OR type LIKE '%$searchQuery%' OR topic LIKE '%$searchQuery%' OR description LIKE '%$searchQuery%' OR cospeakers LIKE '%$searchQuery%' OR audience LIKE '%$searchQuery%' OR experience LIKE '%$searchQuery%' OR question LIKE '%$searchQuery%' OR consent LIKE '%$searchQuery%' OR ip LIKE '%$searchQuery%' Limit 10";

        $results = $wpdb->get_results($sql);

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

        $data = array(
            'name' => sanitize_text_field($speaker['name']),
            'email' => sanitize_text_field($speaker['email']),
            'comment' => sanitize_text_field($speaker['comment']),
            'phone' => sanitize_text_field($speaker['phone']),
            'username' => sanitize_text_field($speaker['username']),
            'social' => sanitize_text_field($speaker['social']),
            'date' => sanitize_text_field($speaker['date']),
            'type' => sanitize_text_field($speaker['type']),
            'topic' => sanitize_text_field($speaker['topic']),
            'description' => esc_html($speaker['description']),
            'status' => sanitize_text_field($speaker['status']),
            'cospeakers' => sanitize_text_field($speaker['cospeakers']),
            'audience' => sanitize_text_field($speaker['audience']),
            'experience' => sanitize_text_field($speaker['experience']),
            'question' => sanitize_text_field($speaker['question']),
            'consent' => sanitize_text_field($speaker['consent']),
            'ip' => sanitize_text_field($speaker['ip'])
        );

        $where = array(
            'id' => $speaker['id']
        );

        $wpdb->update($table_name, $data, $where);
    }

    public function insert($speaker)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speakers';
        $data = array(
            'name' => sanitize_text_field($speaker['name']),
            'email' => sanitize_text_field($speaker['email']),
            'comment' => sanitize_text_field($speaker['comment']),
            'phone' => sanitize_text_field($speaker['phone']),
            'username' => sanitize_text_field($speaker['username']),
            'social' => sanitize_text_field($speaker['social']),
            'date' => sanitize_text_field($speaker['date']),
            'type' => sanitize_text_field($speaker['type']),
            'topic' => sanitize_text_field($speaker['topic']),
            'description' => esc_html($speaker['description']),
            'status' => sanitize_text_field($speaker['status']),
            'cospeakers' => sanitize_text_field($speaker['cospeakers']),
            'audience' => sanitize_text_field($speaker['audience']),
            'experience' => sanitize_text_field($speaker['experience']),
            'question' => sanitize_text_field($speaker['question']),
            'consent' => sanitize_text_field($speaker['consent']),
            'ip' => sanitize_text_field($speaker['ip'])
        );

        $wpdb->insert($table_name, $data);

        if ($wpdb->last_error) {
            dd($wpdb->last_error);
        }
    }
}
