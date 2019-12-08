<?php

defined('MOODLE_INTERNAL') || die();

const DATAFIELD_NOTIFICATION_COLUMN_FIELD_OPTIONS = 'param1';
const DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED = 'param2';

const DATAFIELD_NOTIFICATION_FREQUENCY_NONE = 0;
const DATAFIELD_NOTIFICATION_FREQUENCY_DAILY = 1;
const DATAFIELD_NOTIFICATION_FREQUENCY_WEEKDAYS = 2;
const DATAFIELD_NOTIFICATION_FREQUENCY_WEEKENDS = 3;

const DATAFIELD_NOTIFICATION_CONDITION_NORECORD = 1;

function datafield_notification_getoptions($field) {
    $options = json_decode($field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_OPTIONS});
    if (!$options) {
        $options = new stdClass();
    }

    if (!isset($options->message)) {
        $options->message = 'Notification Message from System';
    }
    if (!isset($options->targets)) {
        $options->targets = [];
    }
    if (!isset($options->frequency)) {
        $options->frequency = DATAFIELD_NOTIFICATION_FREQUENCY_DAILY;
    }
    if (!isset($options->condition)) {
        $options->condition = DATAFIELD_NOTIFICATION_CONDITION_NORECORD;
    }

    return $options;
}

function datafield_notification_getoptionsfrompagerequest() {
    $options = new stdClass();
    $options->message = isset($_REQUEST['options_message']) ? $_REQUEST['options_message'] : '';
    $options->targets = isset($_REQUEST['options_targets']) ? $_REQUEST['options_targets'] : [];
    $options->frequency = isset($_REQUEST['options_frequency']) ? $_REQUEST['options_frequency'] : DATAFIELD_NOTIFICATION_FREQUENCY_NONE;
    $options->condition = isset($_REQUEST['options_condition']) ? $_REQUEST['options_condition'] : null;
    return $options;
}

function datafield_notification_gettargetcheckboxes($users, $selectedusers = []) {
    $str = '';
    foreach ($users as $id => $role) {
        $attr = [
            'type' => 'checkbox',
            'value' => $id,
            'name' => 'options_targets[]'
        ];

        if (in_array($id, $selectedusers)) {
            $attr['checked'] = 'checked';
        }

        $str .= html_writer::div(
            html_writer::tag('label',
                html_writer::start_tag('input', $attr) . $role
            )
        );
    }

    return $str;
}

function datafield_notification_getcheckinterval($frecuency) {
    switch ($frecuency) {
        case DATAFIELD_NOTIFICATION_FREQUENCY_DAILY:
            return 86400;
        case DATAFIELD_NOTIFICATION_FREQUENCY_WEEKDAYS:
            return 86400;
        case DATAFIELD_NOTIFICATION_FREQUENCY_WEEKENDS:
            return 86400;
    }
    return null;
}

function datafield_notification_cronexecute($field) {
    global $DB;

    $options = datafield_notification_getoptions($field);
    $interval = datafield_notification_getcheckinterval($options->frequency);
    if (is_null($interval)) {
        return;
    }

    if (isset($field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED}) && $field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED}) {
        $lastexecuted = $field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED};
    } else {
        $lastexecuted = 0;
    }

    if (time() - $lastexecuted > $interval) {
        // execute

        switch ($options->condition) {
            case DATAFIELD_NOTIFICATION_CONDITION_NORECORD:
                datafield_notification_cronnorecord($field, $options, $interval);
                break;
        }

        $field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED} = mktime(0, 0, 0);
        $DB->update_record('data_fields', $field);
        mtrace('Field #' . $field->id . ' executed');
    } else {
        mtrace('Field #' . $field->id . ' skipped');
    }
}

function datafield_notification_cronnorecord($field, $options, $interval) {
    global $DB;
    if (!count($options->targets)) {
        return;
    }
    $today = date('w');
    if (
        ($options->frequency == DATAFIELD_NOTIFICATION_FREQUENCY_WEEKDAYS
            && ($today == 0 || $today == 6))
        || ($options->frequency == DATAFIELD_NOTIFICATION_FREQUENCY_WEEKENDS
            && ($today > 0 && $today < 6))
    ) {
        mtrace('Field #' . $field->id . ' - not today');
        return;
    }

    $end = time();
    $begin = $end - $interval;
    foreach ($options->targets as $target) {
        $countrecord = $DB->get_record_sql(
            'SELECT COUNT(*) amount FROM {data_records} WHERE dataid = ? AND userid = ? AND timecreated BETWEEN ? AND ?'
        , [$field->dataid, $target, $begin, $end]);
        if (!$countrecord->amount) {
            datafield_notification_sendmessage($field, $options, $target);
        }
    }
}

function datafield_notification_makemessagehtml($field, $options, $target) {
    global $DB;
    $data = $DB->get_record('data', ['id' => $field->dataid]);

    $targetfullname = fullname($target);
    $linktocourse = new moodle_url('/course/view.php', ['id' => $data->course]);
    $linktomodule = new moodle_url('/mod/data/view.php', ['d' => $data->id]);
    $linktocoursehtml = html_writer::link($linktocourse, $linktocourse);
    $linktomodulehtml = html_writer::link($linktomodule, $linktomodule);

    return str_replace(
        ['[[targetfullname]]', '[[linktocourse]]', '[[linktomodule]]'],
        [$targetfullname, $linktocoursehtml, $linktomodulehtml],
        nl2br(htmlspecialchars($options->message)));
}

function datafield_notification_sendmessage($field, $options, $targetid) {
    global $DB;

    $target = $DB->get_record('user', ['id' => $targetid]);
    $fullmessage = datafield_notification_makemessagehtml($field, $options, $target);

    $message = new \core\message\message();
    $message->component = 'datafield_notification';
    $message->name = 'norecordnotification';
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $target;
    $message->subject = $field->name;
    $message->fullmessageformat = FORMAT_HTML;
    $message->fullmessagehtml = $fullmessage;
    $message->smallmessage = strip_tags($fullmessage);
    $message->notification = true;
    $message->courseid = 6;
    message_send($message);
}
