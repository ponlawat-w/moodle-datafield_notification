<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'datafield_notification\task\notification_task',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];
