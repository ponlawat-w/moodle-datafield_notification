<?php

namespace datafield_notification\task;

require_once(__DIR__ . '/../../lib.php');

class notification_task extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('notification_task', 'datafield_notification');
    }

    public function execute() {
        global $DB;
        $fields = $DB->get_records('data_fields', ['type' => 'notification']);
        foreach ($fields as $field) {
            datafield_notification_cronexecute($field);
        }
    }
}
