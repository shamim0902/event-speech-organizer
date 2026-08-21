<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}

class EventModel
{
    private function table()
    {
        global $wpdb;

        return $wpdb->prefix . 'speaker_events';
    }

    /**
     * All events, newest first, each with its applicant and slot counts so the
     * events screen can show them without a second round trip.
     */
    public function get()
    {
        global $wpdb;

        $events = $this->table();
        $speakers = $wpdb->prefix . 'speakers';
        $slots = $wpdb->prefix . 'speakers_slots';

        $results = $wpdb->get_results(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM $speakers AS s WHERE s.event_id = e.id) AS applicants,
                    (SELECT COUNT(*) FROM $speakers AS s WHERE s.event_id = e.id AND s.status = 'approved') AS approved,
                    (SELECT COUNT(*) FROM $slots AS sl WHERE sl.event_id = e.id) AS slots
             FROM $events AS e
             ORDER BY e.id DESC"
        );

        return array('data' => $results ? $results : array());
    }

    public function find($id)
    {
        global $wpdb;

        $id = (int) $id;

        if (!$id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id)
        );
    }

    public function exists($id)
    {
        return (bool) $this->find($id);
    }

    public function insert($event)
    {
        global $wpdb;

        $data = $this->sanitize($event);

        if (!$data['title']) {
            return array('status' => false, 'message' => __('An event title is required.', 'textdomain'));
        }

        $data['created_at'] = current_time('mysql');

        $inserted = $wpdb->insert($this->table(), $data);

        if ($inserted === false) {
            return array('status' => false, 'message' => $wpdb->last_error);
        }

        return array('status' => true, 'id' => (int) $wpdb->insert_id);
    }

    public function update($event)
    {
        global $wpdb;

        $id = isset($event['id']) ? (int) $event['id'] : 0;

        if (!$id) {
            return array('status' => false, 'message' => __('No event to update.', 'textdomain'));
        }

        $data = $this->sanitize($event);

        if (!$data['title']) {
            return array('status' => false, 'message' => __('An event title is required.', 'textdomain'));
        }

        $wpdb->update($this->table(), $data, array('id' => $id));

        return array('status' => true, 'id' => $id);
    }

    /**
     * Deleting an event leaves its applicants and slots behind by default —
     * losing submissions because an event was tidied away would be worse than
     * leaving orphans. Pass $withContent to remove them too.
     */
    public function delete($id, $withContent = false)
    {
        global $wpdb;

        $id = (int) $id;

        if (!$id) {
            return array('status' => false, 'message' => __('No event to delete.', 'textdomain'));
        }

        if ($withContent) {
            $wpdb->delete($wpdb->prefix . 'speakers', array('event_id' => $id));
            $wpdb->delete($wpdb->prefix . 'speakers_slots', array('event_id' => $id));
        }

        $wpdb->delete($this->table(), array('id' => $id));

        WebhookHandler::deleteEventData($id);
        SchedulePage::deleteEventData($id);

        return array('status' => true);
    }

    private function sanitize($event)
    {
        return array(
            'title'       => sanitize_text_field(isset($event['title']) ? $event['title'] : ''),
            'description' => sanitize_textarea_field(isset($event['description']) ? $event['description'] : ''),
            'location'    => sanitize_text_field(isset($event['location']) ? $event['location'] : ''),
            'event_date'  => sanitize_text_field(isset($event['event_date']) ? $event['event_date'] : ''),
            'status'      => $this->sanitizeStatus(isset($event['status']) ? $event['status'] : ''),
        );
    }

    private function sanitizeStatus($status)
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, array('active', 'archived'), true) ? $status : 'active';
    }
}
