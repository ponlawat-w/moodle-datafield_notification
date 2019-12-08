<?php

defined('MOODLE_INTERNAL') or die();

$messageproviders = array(
    'norecordnotification' => array(
        'capability' => 'datafield/notification:norecordnotification',
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'anyotheroutput' => MESSAGE_PERMITTED
        )
    )
);
