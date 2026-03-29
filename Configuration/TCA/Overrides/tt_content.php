<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'label' => 'Twitter',
        'value' => 'twitter',
        'icon' => 'twitter',
        'description' => 'LLL:EXT:xima_twitter_client/Resources/Private/Language/locallang.xlf:tt_content.twitter.description',
        'group' => 'special',
    ],
    'html',
    'after'
);

$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['twitter'] = 'twitter';

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'twitter' => [
        'label' => 'Accounts',
        'config' => [
            'type' => 'group',
            'allowed' => 'tx_ximatwitterclient_domain_model_account',
        ],
    ],
]);

$GLOBALS['TCA']['tt_content']['types']['twitter'] = [
    'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    --palette--;;general,
                    --palette--;;header,
                    twitter,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
                    --palette--;;frames,
                    --palette--;;appearanceLinks,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;language,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;hidden,
                    --palette--;;access,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
                    categories,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                    rowDescription,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,',
];
