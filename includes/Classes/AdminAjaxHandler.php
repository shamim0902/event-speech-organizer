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
            'delete_applicant' => 'deleteApplicant',
            'import_applicants' => 'importApplicants',
            'fluentform_forms' => 'fluentFormForms',
            'fluentform_columns' => 'fluentFormColumns',
            'fluentform_import' => 'fluentFormImport',
            'get_events' => 'getEvents',
            'get_webhook_settings' => 'getWebhookSettings',
            'regenerate_webhook_token' => 'regenerateWebhookToken',
            'get_webhook_mapping' => 'getWebhookMapping',
            'save_webhook_mapping' => 'saveWebhookMapping',
            'save_event' => 'saveEvent',
            'delete_event' => 'deleteEvent',
        );

        if (isset($validRoutes[$route])) {
            do_action('event_speech_organizer/doing_ajax_forms_' . $route);
            return $this->{$validRoutes[$route]}();
        }
        do_action('event_speech_organizer/admin_ajax_handler_catch', $route);
    }

    /**
     * The event every applicant/slot route is scoped to.
     */
    private function currentEventId()
    {
        return isset($_REQUEST['event_id']) ? absint($_REQUEST['event_id']) : 0;
    }

    private function requireEventId()
    {
        $eventId = $this->currentEventId();

        if (!$eventId || !(new EventModel())->exists($eventId)) {
            wp_send_json(array(
                'status' => false,
                'message' => __('A valid event is required.', 'textdomain'),
            ), 400);
        }

        return $eventId;
    }

    public function getEvents()
    {
        wp_send_json((new EventModel())->get());
    }

    /**
     * Everything the settings screen shows about the incoming webhook: one
     * URL per event. Guarded because the URLs embed the secret token that
     * authenticates incoming webhooks.
     */
    public function getWebhookSettings()
    {
        $this->guardImportRequest();

        wp_send_json(array(
            'status' => true,
            'events' => $this->webhookEventUrls(),
        ));
    }

    /**
     * Rotate the webhook secret. Old URLs stop working immediately, so the
     * fresh URL list is returned in the same response for re-sharing.
     */
    public function regenerateWebhookToken()
    {
        $this->guardImportRequest();

        WebhookHandler::regenerateToken();

        wp_send_json(array(
            'status' => true,
            'events' => $this->webhookEventUrls(),
        ));
    }

    /**
     * Everything the per-event field mapper dialog needs: the stored manual
     * mapping, the last captured payload, what the keyword rules would pick
     * for each captured key, the mappable columns, and the webhook URL to
     * test against.
     */
    public function getWebhookMapping()
    {
        $this->guardImportRequest();
        $eventId = $this->requireEventId();

        $lastPayload = WebhookHandler::getLastPayload($eventId);

        $handler = new WebhookHandler();
        $auto = array();

        if (!empty($lastPayload['fields']) && is_array($lastPayload['fields'])) {
            foreach ($lastPayload['fields'] as $key => $value) {
                $auto[$key] = $handler->autoColumnFor($key);
            }
        }

        wp_send_json(array(
            'status'       => true,
            'mapping'      => WebhookHandler::getMapping($eventId),
            'last_payload' => $lastPayload,
            'auto'         => $auto,
            'columns'      => ApplicantModel::getMappableColumns(),
            'url'          => WebhookHandler::getUrl($eventId),
        ));
    }

    public function saveWebhookMapping()
    {
        $this->guardImportRequest();
        $eventId = $this->requireEventId();

        $mapping = json_decode(isset($_REQUEST['mapping']) ? wp_unslash($_REQUEST['mapping']) : '', true);

        if (!is_array($mapping)) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('No mapping to save.', 'textdomain'),
            ), 400);
        }

        wp_send_json(array(
            'status'  => true,
            'mapping' => WebhookHandler::saveMapping($eventId, $mapping),
        ));
    }

    private function webhookEventUrls()
    {
        $events = (new EventModel())->get();
        $rows = array();

        foreach ($events['data'] as $event) {
            $rows[] = array(
                'id'    => (int) $event->id,
                'title' => $event->title,
                'url'   => WebhookHandler::getUrl($event->id),
            );
        }

        return $rows;
    }

    public function saveEvent()
    {
        $this->guardImportRequest();

        $event = isset($_REQUEST['data']) ? (array) wp_unslash($_REQUEST['data']) : array();

        $model = new EventModel();

        $result = empty($event['id']) ? $model->insert($event) : $model->update($event);

        wp_send_json($result, empty($result['status']) ? 400 : 200);
    }

    public function deleteEvent()
    {
        $this->guardImportRequest();

        $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
        $withContent = !empty($_REQUEST['with_content']) && 'false' !== $_REQUEST['with_content'];

        $result = (new EventModel())->delete($id, $withContent);

        wp_send_json($result, empty($result['status']) ? 400 : 200);
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
        $eventId = $this->requireEventId();

        $raw = isset($_REQUEST['rows']) ? wp_unslash($_REQUEST['rows']) : '';
        $rows = json_decode($raw, true);

        if (!is_array($rows) || !$rows) {
            wp_send_json(array(
                'status' => false,
                'message' => __('No rows to import.', 'textdomain'),
            ), 400);
        }

        $applicantModel = new ApplicantModel();

        wp_send_json($applicantModel->importRows($rows, 2, $eventId));
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
        $eventId = $this->requireEventId();

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
        $result = $applicantModel->importRows($rows, $offset + 1, $eventId);
        $result['processed'] = count($rows);

        wp_send_json($result);
    }

    public function editApplicant()
    {
        $applicant = isset($_REQUEST['data']) ? (array) wp_unslash($_REQUEST['data']) : array();

        (new ApplicantModel())->update($applicant);

        wp_send_json(array('status' => true));
    }

    public function deleteApplicant()
    {
        $this->guardImportRequest();
        $eventId = $this->requireEventId();

        $id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;

        $result = (new ApplicantModel())->delete($id, $eventId);

        wp_send_json($result, empty($result['status']) ? 400 : 200);
    }

    public function addApplicant()
    {
        $eventId = $this->requireEventId();

        $applicant = isset($_REQUEST['data']) ? (array) wp_unslash($_REQUEST['data']) : array();
        $applicant['event_id'] = $eventId;

        $result = (new ApplicantModel())->insert($applicant);

        wp_send_json($result, empty($result['status']) ? 400 : 200);
    }

    public function searchSpeakers()
    {
        $search = isset($_REQUEST['search_by']) ? $_REQUEST['search_by'] : '';

        wp_send_json((new ApplicantModel())->searchBy($search, $this->currentEventId()));
    }

    public function getSlots()
    {
        wp_send_json((new SpeakerSlots())->get($this->currentEventId()));
    }

    public function deleteSlot()
    {
        (new SpeakerSlots())->delete(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);

        wp_send_json(array('status' => true));
    }

    public function saveSlots()
    {
        $eventId = $this->requireEventId();

        $slot = isset($_REQUEST['data']) ? (array) wp_unslash($_REQUEST['data']) : array();
        $slot['event_id'] = $eventId;

        $slotModel = new SpeakerSlots();

        if (!empty($slot['id'])) {
            $slotModel->update($slot);
            wp_send_json(array('status' => true, 'id' => (int) $slot['id']));
        }

        $result = $slotModel->insert($slot);

        wp_send_json($result, empty($result['status']) ? 400 : 200);
    }

    protected function getData()
    {
        $request = array(
            'event_id' => $this->currentEventId(),
            'options'  => isset($_REQUEST['options']) ? $_REQUEST['options'] : array(),
        );

        wp_send_json((new ApplicantModel())->get($request));
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
