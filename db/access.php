<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'datafield/notification:norecordnotification' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'user' => CAP_ALLOW
        )
    )
);
