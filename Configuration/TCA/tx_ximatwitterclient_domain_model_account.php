<?php

use Xima\XimaTwitterClient\FetchType\LatestTweets;

return [
    'ctrl' => [
        'title' => 'Twitter account',
        'label' => 'username',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'searchFields' => 'username',
        'iconfile' => 'EXT:xima_twitter_client/Resources/Public/Icons/account.svg',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
    ],
    'types' => [
        0 => [
            'showitem' => 'username, fetch_type, fetch_options, max_results',
        ],
    ],
    'columns' => [
        'username' => [
            'label' => 'Username',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'fetch_type' => [
            'label' => 'Type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'Latest tweets of user',
                        'value' => LatestTweets::class,
                    ],
                ],
            ],
        ],
        'fetch_options' => [
            'label' => 'Options',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => [
                    ['label' => 'Exclude replies', 'value' => 'replies'],
                    ['label' => 'Exclude retweets', 'value' => 'retweets'],
                ],
            ],
        ],
        'max_results' => [
            'label' => 'Max results',
            'config' => [
                'type' => 'number',
                'default' => 10,
                'required' => true,
            ],
        ],
    ],
];
