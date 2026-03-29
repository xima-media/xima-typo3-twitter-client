<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::registerPageTSConfigFile(
    'xima_twitter_client',
    'Configuration/TSconfig/page.tsconfig',
    'XIMA Twitter Client'
);

$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    0 => 'Twitter',
    1 => 'tw',
    2 => 'folder-contains-twitter',
];
$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-tw'] = 'folder-contains-twitter';
