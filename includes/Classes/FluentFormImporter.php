<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads Fluent Forms submissions so they can be imported as applicants.
 *
 * Submission responses are stored as a JSON blob whose shape varies per form:
 * scalars, list arrays (checkboxes), and nested maps (the `names` and
 * `address` elements). This class flattens that into a stable set of dotted
 * column keys that the mapping UI can offer.
 *
 * Fluent Forms' own classes are used only to decorate columns with friendly
 * labels; everything functional reads the tables directly, so an import still
 * works if their internal API moves.
 */
class FluentFormImporter
{
    const FORMS_TABLE = 'fluentform_forms';
    const SUBMISSIONS_TABLE = 'fluentform_submissions';

    /**
     * Response keys Fluent Forms writes for its own bookkeeping.
     * Everything starting with an underscore is internal.
     */
    private static $sampleSize = 25;

    public static function isAvailable()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Every form that has at least one submission, newest first.
     */
    public function getForms()
    {
        global $wpdb;

        $formsTable = $wpdb->prefix . self::FORMS_TABLE;
        $submissionsTable = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        $rows = $wpdb->get_results(
            "SELECT f.id, f.title, COUNT(s.id) AS submissions
             FROM {$formsTable} AS f
             INNER JOIN {$submissionsTable} AS s ON s.form_id = f.id
             GROUP BY f.id, f.title
             ORDER BY f.id DESC"
        );

        return array_map(function ($row) {
            return array(
                'id'          => (int) $row->id,
                'title'       => $row->title,
                'submissions' => (int) $row->submissions,
            );
        }, (array) $rows);
    }

    public function countSubmissions($formId)
    {
        global $wpdb;

        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE form_id = %d", $formId)
        );
    }

    /**
     * Build the list of mappable source columns for a form by flattening a
     * sample of its submissions and unioning the keys. Deriving columns from
     * real responses (rather than the form definition) means the offered
     * columns are exactly the ones that carry data.
     */
    public function getColumns($formId)
    {
        global $wpdb;

        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT response, created_at FROM {$table} WHERE form_id = %d ORDER BY id DESC LIMIT %d",
                $formId,
                self::$sampleSize
            )
        );

        $labels = $this->getFieldLabels($formId);
        $columns = array();

        foreach ((array) $rows as $row) {
            $flat = $this->flattenResponse($row->response);

            foreach ($flat as $key => $value) {
                if (!isset($columns[$key])) {
                    $columns[$key] = array(
                        'value'  => $key,
                        'label'  => $this->labelFor($key, $labels),
                        'sample' => '',
                    );
                }

                if ($columns[$key]['sample'] === '' && $value !== '') {
                    $columns[$key]['sample'] = $this->truncate($value, 60);
                }
            }
        }

        $columns = array_values($columns);

        // Submission metadata, offered after the form's own fields.
        foreach ($this->metaColumns() as $meta) {
            $columns[] = $meta;
        }

        return $columns;
    }

    /**
     * Read a slice of submissions, flattened and mapped onto applicant columns.
     *
     * @param int   $formId
     * @param array $mapping  applicant column => submission column key
     * @param int   $offset
     * @param int   $limit
     * @return array list of applicant rows ready for ApplicantModel::importRows()
     */
    public function getMappedRows($formId, $mapping, $offset, $limit)
    {
        global $wpdb;

        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, response, created_at, status, ip, city, country
                 FROM {$table}
                 WHERE form_id = %d
                 ORDER BY id ASC
                 LIMIT %d OFFSET %d",
                $formId,
                $limit,
                $offset
            )
        );

        $importable = ApplicantModel::getImportableColumns();
        $mapped = array();

        foreach ((array) $rows as $row) {
            $flat = $this->flattenResponse($row->response);
            $flat = array_merge($flat, $this->metaValues($row));

            $record = array();

            foreach ($importable as $column) {
                $source = isset($mapping[$column]) ? $mapping[$column] : '';
                $record[$column] = ($source !== '' && isset($flat[$source])) ? $flat[$source] : '';
            }

            $mapped[] = $record;
        }

        return $mapped;
    }

    /**
     * Flatten a submission response into key => string pairs.
     *
     * Nested maps produce both a joined parent value (`names`) and dotted
     * children (`names.first_name`), so a form can be mapped either way.
     */
    private function flattenResponse($response)
    {
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return array();
        }

        $flat = array();

        foreach ($decoded as $key => $value) {
            // Underscore-prefixed keys are Fluent Forms internals: nonces,
            // referrers, embedded post ids.
            if (strpos((string) $key, '_') === 0) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$key] = (string) $value;
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            if ($this->isList($value)) {
                $flat[$key] = implode(', ', array_map(function ($item) {
                    return is_scalar($item) ? (string) $item : '';
                }, $value));
                continue;
            }

            $parts = array();

            // Reserve the parent's slot first so it lists above its children.
            $flat[$key] = '';

            foreach ($value as $subKey => $subValue) {
                if (!is_scalar($subValue) && $subValue !== null) {
                    continue;
                }

                $subValue = (string) $subValue;
                $flat[$key . '.' . $subKey] = $subValue;

                if ($subValue !== '') {
                    $parts[] = $subValue;
                }
            }

            // The `input_name` element splits into name parts, which read as a
            // single name; anything else (address) reads better comma-joined.
            $glue = $this->isNameGroup($value) ? ' ' : ', ';
            $flat[$key] = implode($glue, $parts);
        }

        return $flat;
    }

    private function isList($value)
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function isNameGroup($value)
    {
        $nameKeys = array('first_name', 'middle_name', 'last_name');

        foreach (array_keys($value) as $key) {
            if (!in_array($key, $nameKeys, true)) {
                return false;
            }
        }

        return true;
    }

    private function metaColumns()
    {
        return array(
            array('value' => '__created_at', 'label' => 'Submission: date', 'sample' => ''),
            array('value' => '__submission_id', 'label' => 'Submission: ID', 'sample' => ''),
            array('value' => '__status', 'label' => 'Submission: status', 'sample' => ''),
            array('value' => '__ip', 'label' => 'Submission: IP', 'sample' => ''),
            array('value' => '__city', 'label' => 'Submission: city', 'sample' => ''),
            array('value' => '__country', 'label' => 'Submission: country', 'sample' => ''),
        );
    }

    private function metaValues($row)
    {
        return array(
            '__created_at'    => (string) $row->created_at,
            '__submission_id' => (string) $row->id,
            '__status'        => (string) $row->status,
            '__ip'            => (string) $row->ip,
            '__city'          => (string) $row->city,
            '__country'       => (string) $row->country,
        );
    }

    /**
     * Friendly labels from Fluent Forms, if its parser is loaded. Purely
     * cosmetic — the import works off the raw keys either way.
     */
    private function getFieldLabels($formId)
    {
        if (!class_exists('\FluentForm\App\Modules\Form\FormFieldsParser')) {
            return array();
        }

        try {
            $form = wpFluent()->table(self::FORMS_TABLE)->find($formId);

            if (!$form) {
                return array();
            }

            $inputs = \FluentForm\App\Modules\Form\FormFieldsParser::getEntryInputs($form, array('admin_label'));

            $labels = array();

            foreach ((array) $inputs as $key => $input) {
                if (!empty($input['admin_label'])) {
                    $labels[$key] = $input['admin_label'];
                }
            }

            return $labels;
        } catch (\Exception $e) {
            return array();
        } catch (\Throwable $e) {
            return array();
        }
    }

    private function labelFor($key, $labels)
    {
        if (isset($labels[$key])) {
            return $labels[$key];
        }

        // Dotted child of a labelled parent, e.g. names.first_name -> "Name → first name".
        if (strpos($key, '.') !== false) {
            list($parent, $child) = explode('.', $key, 2);
            $child = ucfirst(str_replace('_', ' ', $child));

            if (isset($labels[$parent])) {
                return $labels[$parent] . ' → ' . $child;
            }

            return ucfirst(str_replace('_', ' ', $parent)) . ' → ' . $child;
        }

        return ucfirst(str_replace(array('_', '-'), ' ', $key));
    }

    private function truncate($value, $length)
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        if (function_exists('mb_strlen') && mb_strlen($value) > $length) {
            return mb_substr($value, 0, $length) . '…';
        }

        if (strlen($value) > $length) {
            return substr($value, 0, $length) . '…';
        }

        return $value;
    }
}
