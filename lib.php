<?php

defined('MOODLE_INTERNAL') || die();

const DATAFIELD_NOTIFICATION_COLUMN_FIELD_OPTIONS = 'param1';
const DATAFIELD_NOTIFICATION_COLUMN_FIELD_LASTEXECUTED = 'param2';

const DATAFIELD_NOTIFICATION_FREQUENCY_NONE = 0;
const DATAFIELD_NOTIFICATION_FREQUENCY_DAILY = 1;
const DATAFIELD_NOTIFICATION_FREQUENCY_WEEKDAYS = 2;
const DATAFIELD_NOTIFICATION_FREQUENCY_WEEKENDS = 3;

const DATAFIELD_NOTIFICATION_CONDITION_NORECORD = 1;
const DATAFIELD_NOTIFICATION_CONDITION_EMPTYFIELD = 2;

const DATAFIELD_NOTIFICATION_CHECKTYPE_ALL = 1;
const DATAFIELD_NOTIFICATION_CHECKTYPE_DAY = 2;

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
    if (!isset($options->checktype)) {
        $options->checktype = DATAFIELD_NOTIFICATION_CHECKTYPE_DAY;
    }

    return $options;
}

function datafield_notification_getcourseid($field) {
    global $DB;
    $data = $DB->get_record('data', ['id' => $field->dataid]);
    return $data->course;
}

function datafield_notification_getotherfields($dataid) {
    global $DB;
    return $DB->get_records_sql(
        'SELECT * FROM {data_fields} WHERE dataid = ? AND type != \'notification\''
    , [$dataid]);
}

function datafield_notification_getfieldsdropdown($fields, $optionsobj, $id, $name = 'options_field') {
    $options = [];
    $selectedid = isset($optionsobj->field) ? $optionsobj->field : 0;
    foreach ($fields as $field) {
        $attr = [
            'value' => $field->id
        ];
        if ($field->id == $selectedid) {
            $attr['selected'] = 'selected';
        }
        $options[] = html_writer::tag('option', $field->name, $attr);
    }
    return html_writer::tag('select', implode('', $options), ['id' => $id, 'name' => $name]);
}

function datafield_notification_getoptionsfrompagerequest() {
    $options = new stdClass();
    $options->message = isset($_REQUEST['options_message']) ? $_REQUEST['options_message'] : '';
    $options->targets = isset($_REQUEST['options_targets']) ? $_REQUEST['options_targets'] : [];
    $options->frequency = isset($_REQUEST['options_frequency']) ? $_REQUEST['options_frequency'] : DATAFIELD_NOTIFICATION_FREQUENCY_NONE;
    $options->checktype = isset($_REQUEST['options_checktype']) ? $_REQUEST['options_checktype'] : DATAFIELD_NOTIFICATION_CHECKTYPE_DAY;
    $options->condition = isset($_REQUEST['options_condition']) ? $_REQUEST['options_condition'] : null;

    if ($options->condition == DATAFIELD_NOTIFICATION_CONDITION_EMPTYFIELD) {
        $options->field = isset($_REQUEST['options_field']) ? $_REQUEST['options_field'] : 0;
        $options->ownerships = isset($_REQUEST['options_ownerships']) ? $_REQUEST['options_ownerships'] : [];
    }

    return $options;
}

function datafield_notification_getuserscheckboxes($users, $selectedusers = [], $name = 'options_targets[]') {
    $str = '';
    foreach ($users as $id => $role) {
        $attr = [
            'type' => 'checkbox',
            'value' => $id,
            'name' => $name
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

        switch ($options->condition) {
            case DATAFIELD_NOTIFICATION_CONDITION_NORECORD:
                datafield_notification_cronnorecord($field, $options, $interval);
                break;
            case DATAFIELD_NOTIFICATION_CONDITION_EMPTYFIELD:
                datafield_notification_cronemptyfield($field, $options, $interval);
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

    $end = time();
    $begin = $options->checktype == DATAFIELD_NOTIFICATION_CHECKTYPE_DAY ? $end - $interval : 0;
    foreach ($options->targets as $target) {
        $countrecord = $DB->get_record_sql(
            'SELECT COUNT(*) amount FROM {data_records} WHERE dataid = ? AND userid = ? AND timecreated BETWEEN ? AND ?'
        , [$field->dataid, $target, $begin, $end]);
        if (!$countrecord->amount) {
            datafield_notification_sendnoentrymessage($field, $options, $target);
        }
    }
}

function datafield_notification_cronemptyfield($field, $options, $interval) {
    global $DB;
    if (!count($options->targets)) {
        return;
    }
    if (!count($options->ownerships)) {
        return;
    }

    $end = time();
    $begin = $options->checktype == DATAFIELD_NOTIFICATION_CHECKTYPE_DAY ? $end - $interval : 0;

    $ownershipsparams = [];
    for ($i = 0; $i < count($options->ownerships); $i++) {
        $ownershipsparams[] = '?';
    }

    $records = $DB->get_records_sql(
        'SELECT * FROM {data_records} WHERE userid IN (' . implode(',', $ownershipsparams) .') AND dataid = ? AND timecreated BETWEEN ? AND ?'
    , array_merge($options->ownerships, [$field->dataid, $begin, $end]));
    foreach ($records as $record) {
        $content = $DB->get_record('data_content', ['fieldid' => $options->field, 'recordid' => $record->id]);
        if (!$content || !$content->content || !trim($content->content)) {
            datafield_notification_sendemptyfieldmessage($record, $field, $options);
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

function datafield_notification_emptyfieldmakemessagehtml($message, $record) {
    global $DB;

    $owner = $DB->get_record('user', ['id' => $record->userid]);

    $ownerfullname = fullname($owner);
    $linktoentry = new moodle_url('/mod/data/view.php', ['rid' => $record->id, 'mode' => 'single']);
    $linktoentryhtml = html_writer::link($linktoentry, $linktoentry);

    return str_replace(
        ['[[ownerfullname]]', '[[linktoentry]]'],
        [$ownerfullname, $linktoentryhtml],
        $message
    );
}

function datafield_notification_sendemptyfieldmessage($record, $field, $options) {
    global $DB;
    foreach ($options->targets as $targetid) {
        $target = $DB->get_record('user', ['id' => $targetid]);
        $fullmessage = datafield_notification_makemessagehtml($field, $options, $target);
        $fullmessage = datafield_notification_emptyfieldmakemessagehtml($fullmessage, $record);

        datafield_notification_sendmessage(
            'datafield_notification',
            'emptyfieldnotification',
            $target,
            $field->name,
            $fullmessage,
            datafield_notification_getcourseid($field)
        );
    }
}

function datafield_notification_sendnoentrymessage($field, $options, $targetid) {
    global $DB;

    $target = $DB->get_record('user', ['id' => $targetid]);
    $fullmessage = datafield_notification_makemessagehtml($field, $options, $target);

    datafield_notification_sendmessage(
        'datafield_notification',
        'norecordnotification',
        $target,
        $field->name,
        $fullmessage,
        datafield_notification_getcourseid($field)
    );
}

function datafield_notification_sendmessage($component, $name, $target, $subject, $fullmessage, $courseid, $notification = true) {
    $message = new \core\message\message();
    $message->component = $component;
    $message->name = $name;
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $target;
    $message->subject = $subject;
    $message->fullmessageformat = FORMAT_HTML;
    $message->fullmessagehtml = $fullmessage;
    $message->smallmessage = strip_tags($fullmessage);
    $message->notification = $notification;
    $message->courseid = $courseid;
    message_send($message);
}
