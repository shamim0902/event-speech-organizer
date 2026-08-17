<?php

namespace EventSpeechOrganizer\Classes;

if (!defined('ABSPATH')) {
    exit;
}


class SpeakerSlots
{
    private function table()
    {
        global $wpdb;

        return $wpdb->prefix . 'speakers_slots';
    }

    public function get($eventId = 0)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE event_id = %d ORDER BY id ASC",
            (int) $eventId
        ));

        $results = $results ? $results : array();

        foreach ($results as $key => $value) {
            $results[$key]->speakers = json_decode($value->speakers);
        }

        return array(
            'data' => $results
        );
    }

    public function delete($id)
    {
        global $wpdb;

        $wpdb->delete($this->table(), array('id' => (int) $id));
    }

    public function update($data)
    {
        global $wpdb;

        $id = isset($data['id']) ? (int) $data['id'] : 0;

        if (!$id) {
            return;
        }

        $data = $this->sanitize($data);

        $wpdb->update($this->table(), $data, array('id' => $id));
    }

    public function insert($data)
    {
        global $wpdb;

        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : 0;

        $data = $this->sanitize($data);
        $data['event_id'] = $eventId;

        $inserted = $wpdb->insert($this->table(), $data);

        if ($inserted === false) {
            return array(
                'status' => false,
                'message' => $wpdb->last_error
            );
        }

        return array('status' => true, 'id' => (int) $wpdb->insert_id);
    }

    /**
     * Keeps only real columns. `visible` used to leak in from the delete
     * popover's local state, and `id`/`event_id` are handled by the caller.
     */
    private function sanitize($data)
    {
        $speakers = isset($data['speakers']) ? (array) $data['speakers'] : array();
        $speakers = array_map('sanitize_text_field', $speakers);

        return array(
            'talk_type' => sanitize_text_field(isset($data['talk_type']) ? $data['talk_type'] : ''),
            'name'      => sanitize_text_field(isset($data['name']) ? $data['name'] : ''),
            'from'      => sanitize_text_field(isset($data['from']) ? $data['from'] : ''),
            'to'        => sanitize_text_field(isset($data['to']) ? $data['to'] : ''),
            'speakers'  => wp_json_encode(array_values($speakers)),
        );
    }
}
