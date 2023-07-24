<?php

spl_autoload_register(
    function ($class) {
        static $classes = null;
        if ($classes === null) {
            $classes = array(
                'issuecrossrefxmlconferencefilter' => '/filter/IssueCrossrefXmlConferenceFilter.inc.php',
                'papercrossrefconferenceXmlfilter' => '/filter/PaperCrossrefXmlConferenceFilter.inc.php'
            );
        }
        $cn = strtolower($class);
        if (isset($classes[$cn])) {
            require __DIR__ . $classes[$cn];
        }
    },
    true,
    false
);
