<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=='BE')	{
	t3lib_extMgm::insertModuleFunction(
		'web_func',
		'tx_wizardcrpagetree_webfunc',
		t3lib_extMgm::extPath($_EXTKEY).'class.tx_wizardcrpagetree_webfunc.php',
		'LLL:EXT:wizard_crpagetree/locallang.php:wiz_crMany',
		'wiz'
	);
	t3lib_extMgm::addLLrefForTCAdescr('_MOD_web_func','EXT:wizard_crpagetree/locallang_csh.xml');
}
?>
