<?php

namespace EventSpeechOrganizer\Classes;

use EventSpeechOrganizer\Classes\ApplicantModel;
use EventSpeechOrganizer\Classes\SpeakerSlots;

class AdminAjaxHandler
{
    public function registerEndpoints()
    {
        add_action('wp_ajax_event_speech_organizer_admin_ajax', array($this, 'handleEndPoint'));
    }
    public function handleEndPoint()
    {
        $route = sanitize_text_field($_REQUEST['route']);

        $validRoutes = array(
            'get_data' => 'getData',
            // 'save' => 'saveSpeaker',
            'update_status' => 'updateStatus',
            'get_slots' => 'getSlots',
            'save_slots' => 'saveSlots',
            'delete_slot' => 'deleteSlot',
            'search_speakers' => 'searchSpeakers',
            'add_applicant' => 'addApplicant',
            'edit_applicant' => 'editApplicant',
            'import_applicants' => 'importApplicants',
            'fluentform_forms' => 'fluentFormForms',
            'fluentform_columns' => 'fluentFormColumns',
            'fluentform_import' => 'fluentFormImport',
        );

        if (isset($validRoutes[$route])) {
            do_action('event_speech_organizer/doing_ajax_forms_' . $route);
            return $this->{$validRoutes[$route]}();
        }
        do_action('event_speech_organizer/admin_ajax_handler_catch', $route);
    }

    /**
     * Bulk import applicants from a CSV parsed in the browser.
     *
     * Rows arrive as one JSON string rather than a nested form array: a 200-row
     * batch of 15 fields would be 3,000 input vars, well past PHP's default
     * max_input_vars of 1,000, and PHP truncates that silently.
     *
     * NOTE: this route verifies a nonce and capability. The other routes in
     * this class still do neither — see the security backlog.
     */
    public function importApplicants()
    {
        $this->guardImportRequest();

        $raw = isset($_REQUEST['rows']) ? wp_unslash($_REQUEST['rows']) : '';
        $rows = json_decode($raw, true);

        if (!is_array($rows) || !$rows) {
            wp_send_json(array(
                'status' => false,
                'message' => __('No rows to import.', 'textdomain'),
            ), 400);
        }

        $applicantModel = new ApplicantModel();

        wp_send_json($applicantModel->importRows($rows));
    }

    /**
     * Shared guard for the import routes. Returns nothing and halts the
     * request when the caller is not allowed.
     */
    private function guardImportRequest()
    {
        if (!AccessControl::hasTopLevelMenuPermission()) {
            wp_send_json(array(
                'status' => false,
                'message' => __('You are not allowed to import applicants.', 'textdomain'),
            ), 403);
        }

        if (!check_ajax_referer('event_speech_organizer_admin', 'nonce', false)) {
            wp_send_json(array(
                'status' => false,
                'message' => __('Security check failed. Please reload the page and try again.', 'textdomain'),
            ), 403);
        }
    }

    private function guardFluentForm()
    {
        $this->guardImportRequest();

        if (!FluentFormImporter::isAvailable()) {
            wp_send_json(array(
                'status' => false,
                'message' => __('Fluent Forms is not installed on this site.', 'textdomain'),
            ), 400);
        }
    }

    public function fluentFormForms()
    {
        $this->guardFluentForm();

        $importer = new FluentFormImporter();

        wp_send_json(array(
            'status' => true,
            'forms' => $importer->getForms(),
        ));
    }

    public function fluentFormColumns()
    {
        $this->guardFluentForm();

        $formId = isset($_REQUEST['form_id']) ? absint($_REQUEST['form_id']) : 0;

        if (!$formId) {
            wp_send_json(array(
                'status' => false,
                'message' => __('No form selected.', 'textdomain'),
            ), 400);
        }

        $importer = new FluentFormImporter();

        wp_send_json(array(
            'status' => true,
            'columns' => $importer->getColumns($formId),
            'total' => $importer->countSubmissions($formId),
        ));
    }

    /**
     * Import one slice of a form's submissions. The browser sends the mapping
     * plus an offset and the server reads, flattens and inserts that slice —
     * submissions never round-trip through the client.
     */
    public function fluentFormImport()
    {
        $this->guardFluentForm();

        $formId = isset($_REQUEST['form_id']) ? absint($_REQUEST['form_id']) : 0;
        $offset = isset($_REQUEST['offset']) ? absint($_REQUEST['offset']) : 0;
        $limit = isset($_REQUEST['limit']) ? absint($_REQUEST['limit']) : 100;
        $limit = min(max($limit, 1), 500);

        $mapping = json_decode(isset($_REQUEST['mapping']) ? wp_unslash($_REQUEST['mapping']) : '', true);

        if (!$formId || !is_array($mapping)) {
            wp_send_json(array(
                'status' => false,
                'message' => __('A form and a field mapping are required.', 'textdomain'),
            ), 400);
        }

        $importer = new FluentFormImporter();
        $rows = $importer->getMappedRows($formId, $mapping, $offset, $limit);

        if (!$rows) {
            wp_send_json(array(
                'status' => true,
                'imported' => 0,
                'duplicates' => 0,
                'invalid' => 0,
                'failed' => 0,
                'issues' => array(),
                'processed' => 0,
            ));
        }

        $applicantModel = new ApplicantModel();
        // Report the 1-based submission position within the form, not the slice.
        $result = $applicantModel->importRows($rows, $offset + 1);
        $result['processed'] = count($rows);

        wp_send_json($result);
    }

    public function editApplicant()
    {
        $applicant = $_REQUEST['data'];
        $applicantModel = new ApplicantModel();
        $applicantModel->update($applicant);
    }


    public function addApplicant()
    {
        $applicant = $_REQUEST['data'];
        $applicantModel = new ApplicantModel();
        $applicant['question'] = '';
        $applicant['consent'] = '';
        $applicant['ip'] = '';

        $applicantModel->insert($applicant);
    }

    public function searchSpeakers()
    {
        $speakerModel = new ApplicantModel();
        $eventSpeechOrganizer = $speakerModel->searchBy($_REQUEST['search_by']);
        wp_send_json($eventSpeechOrganizer);
    }

    public function getSlots()
    {
        $speakerModel = new SpeakerSlots();
        $slots = $speakerModel->get();
        wp_send_json($slots);
    }

    public function deleteSlot()
    {
        $speakerModel = new SpeakerSlots();
        $speakerModel->delete($_REQUEST['id']);
    }

    public function saveSlots()
    {
        $slot = $_REQUEST['data'];
        $speakerModel = new SpeakerSlots();

        $speakers = json_encode($slot['speakers']);

        foreach ($slot as $key => $value) {
            $slot[$key] = sanitize_text_field($value);
        }

        $slot['speakers'] = $speakers;

        if (isset($slot['id'])) {
            return $speakerModel->update($slot);
        }

        $speakerModel->insert($slot);
    }

    protected function getData()
    {
        $speakerModel = new ApplicantModel();
        $speakers = $speakerModel->get($_REQUEST);
        wp_send_json($speakers);
    }

    public function saveSpeaker()
    {
        $speakers = $_REQUEST['data'];

        $speakerModel = new ApplicantModel();

        foreach ($speakers as $speaker) {
            $speakerModel->insert($speaker);
        }
    }

    public function updateStatus()
    {
        $speakerModel = new ApplicantModel();
        $speakerModel->updateStatus($_REQUEST['options']);
    }
}
