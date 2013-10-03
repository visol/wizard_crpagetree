<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (TYPO3_MODE == 'BE') {
	t3lib_extMgm::insertModuleFunction(
		'web_func',
		'tx_wizardcrpagetree_Webfunc_CreatePageTree',
		t3lib_extMgm::extPath($_EXTKEY) . 'Classes/Webfunc/CreatePageTree.php',
		'LLL:EXT:wizard_crpagetree/Resources/Private/Language/locallang.xml:wiz_crPageTree',
		'wiz'
	);
	t3lib_extMgm::addLLrefForTCAdescr('_MOD_web_func', 'EXT:wizard_crpagetree/Resources/Private/Language/ContextSensitiveHelp/default.xml');
}
?>
