<?php

require_once(__DIR__ . '/lib.php');

class data_field_notification extends data_field_base {

    public $type = 'notification';

    public function __construct($field = 0, $data = 0, $cm = 0) {
        parent::__construct($field, $data, $cm);
        $this->copy_icon();
    }

    private function copy_icon() {
        if (!file_exists(__DIR__ . '/../../pix/field/notification.svg')
            && is_writable(__DIR__ . '/../../pix/field/')) {
            copy(__DIR__ . '/pix/icon.svg', __DIR__ . '/../../pix/field/notification.svg');
        }
    }

    function insert_field() {
        $this->field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_OPTIONS} = json_encode(datafield_notification_getoptionsfrompagerequest());
        return parent::insert_field();
    }

    function update_field() {
        $this->field->{DATAFIELD_NOTIFICATION_COLUMN_FIELD_OPTIONS} = json_encode(datafield_notification_getoptionsfrompagerequest());
        return parent::update_field();
    }

    function display_search_field() {
        return '';
    }

    function display_add_field($recordid = 0, $formdata = null) {
        return '';
    }

    function display_edit_field() {
        global $CFG, $OUTPUT, $COURSE, $PAGE;

        $PAGE->requires->js(new moodle_url('/mod/data/field/notification/mod.js'));

        $coursecontext = context_course::instance($COURSE->id);
        $enrolledusers = get_enrolled_users($coursecontext, '', 0, 'u.*', 'firstname, lastname');
        $users = [];
        foreach ($enrolledusers as $user) {
            $users[$user->id] = fullname($user);
        }

        $options = datafield_notification_getoptions($this->field);

        if (empty($this->field)) {   // No field has been defined yet, try and make one
            $this->define_default_field();
        }
        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

        echo '<form id="editfield" action="' . $CFG->wwwroot . '/mod/data/field.php" method="post">' . "\n";
        echo '<input type="hidden" name="d" value="' . $this->data->id . '" />' . "\n";
        if (empty($this->field->id)) {
            echo '<input type="hidden" name="mode" value="add" />' . "\n";
            $savebutton = get_string('add');
        } else {
            echo '<input type="hidden" name="fid" value="' . $this->field->id . '" />' . "\n";
            echo '<input type="hidden" name="mode" value="update" />' . "\n";
            $savebutton = get_string('savechanges');
        }
        echo '<input type="hidden" name="type" value="' . $this->type . '" />'."\n";
        echo '<input name="sesskey" value="' . sesskey() . '" type="hidden" />'."\n";

        echo $OUTPUT->heading($this->name(), 3);

        require_once($CFG->dirroot . '/mod/data/field/' . $this->type . '/mod.html');

        echo '<div class="mdl-align">';
        echo '<input type="submit" class="btn btn-primary" value="' . $savebutton . '" />'."\n";
        echo '<input type="submit" class="btn btn-secondary" name="cancel" value="' . get_string('cancel') . '" />' . "\n";
        echo '</div>';

        echo '</form>';

        echo $OUTPUT->box_end();
    }

    public function parse_search_field($defaults = null) {
        $param = 'f_' . $this->field->id;
        if (empty($defaults[$param])) {
            $defaults = array($param => '');
        }
        return optional_param($param, $defaults[$param], PARAM_NOTAGS);
    }

}
