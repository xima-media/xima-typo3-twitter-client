<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Twitter client',
    'description' => 'Download and display tweets from Twitter',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.4.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Xima\\XimaTwitterClient\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'author' => 'Maik Schneider',
    'author_email' => 'maik.schneider@xima.de',
    'author_company' => 'XIMA Media GmbH',
    'version' => '3.0.0',
];
