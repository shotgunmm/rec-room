<?php

return [
    'author'         => 'Propagate',
    'author_url'     => 'https://propagate.com',
    'name'           => 'Event Calendar',
    'description'    => 'Calendar and events add-on for ExpressionEngine 7 with RRULE recurrence and EE-native category integration',
    'version'        => '2.0.0',
    'namespace'      => 'Propagate\EventCalendar',
    'settings_exist' => 'y',
    'has_cp_backend' => 'y',
    'models'         => [
        'Event'          => 'Model\Event',
        'EventException' => 'Model\EventException',
    ],
];
