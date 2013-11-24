<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Michiel Roos <michiel@maxserv.nl>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Contains class for "Create page tree" wizard
 *
 * $Id$
 *
 * @author   Michiel Roos <michiel@maxserv.nl>
 *
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   68: class tx_wizardcrpagetree_Webfunc_CreatePageTree extends t3lib_extobjbase
 *   74:     public function main()
 *  205:     private function compressArray($data)
 *  226:     private function getArray($data, $oldLevel = 0, $ic)
 *  280:     private function reverseArray($data)
 *  301:     private function filterComments($data)
 *  338:     private function displayCreatForm()
 *  374:     private function getIndentationChar()
 *  396:     private function getSeparationChar()
 *  421:     private function getExtraFields()
 *  436:     private function getLanguageLabel($label)
 *  445:     public function helpBubble()
 *
 * TOTAL FUNCTIONS: 11
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates the "Create pagetree" wizard
 *
 * @author   Michiel Roos <extensions@donationbasedhosting.org>
 * @package TYPO3
 * @subpackage tx_wizardcrpagetree
 */
class tx_wizardcrpagetree_Webfunc_CreatePageTree extends \TYPO3\CMS\Backend\Module\AbstractFunctionModule {
	/**
	 * Main function creating the content for the module.
	 *
	 * @return	string		HTML content for the module
	 */
	public function main() {
		$theCode = '';
		$pageTree = Array();
			// create new pages here?
		$pRec = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('pages', $this->pObj->id, 'uid', ' AND ' . $GLOBALS['BE_USER']->getPagePermsClause(8));
		$sysPages = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Page\PageRepository');
		$menuItems = $sysPages->getMenu($this->pObj->id);
		if (is_array($pRec)) {
			if (GeneralUtility::_POST('newPageTree') === 'submit') {
				$data = explode("\r\n", GeneralUtility::_POST('data'));
				$data = $this->filterComments($data);
				if (count($data)) {
					if (GeneralUtility::_POST('createInListEnd')) {
						$endI = end($menuItems);
						$thePid = - intval($endI['uid']);
						if (!$thePid) {
							$thePid = $this->pObj->id;
						}
					} else {
							// get parent pid
						$thePid = $this->pObj->id;
					}

					$ic = $this->getIndentationChar();
					$sc = $this->getSeparationChar();
					$ef = $this->getExtraFields();

						// Reverse the ordering of the data
					$originalData = $this->getArray($data, 0, $ic);
					$reversedData = $this->reverseArray($originalData);
					$data = $this->compressArray($reversedData);
						//$data = $this->compressArray($originalData);

					if ($data) {
						$pageIndex = count($data);
						$sorting = count($data);
						$oldLevel = 0;
						$parentPid = array();
						$currentPid = 0;
						while (list($k, $line) = each($data)) {
							if (trim($line)) {
									// What level are we on?
								preg_match('/^' . $ic . '*/', $line, $regs);
								$level = strlen($regs[0]);

								if ($level == 0) {
									$currentPid = $thePid;
									$parentPid[$level] = $thePid;
								} elseif ($level > $oldLevel) {
									$currentPid = 'NEW' . ($pageIndex - 1);
									$parentPid[$level] = $pageIndex - 1;
								} elseif ($level === $oldLevel) {
									$currentPid = 'NEW' . $parentPid[$level];
								} elseif ($level < $oldLevel) {
									$currentPid = 'NEW' . $parentPid[$level];
								}

									// Get title and additional field values
								$parts = GeneralUtility::trimExplode($sc, $line);

								$pageTree['pages']['NEW' . $pageIndex]['title'] = ltrim($parts[0], $ic);
								$pageTree['pages']['NEW' . $pageIndex]['pid'] = $currentPid;
								$pageTree['pages']['NEW' . $pageIndex]['sorting'] = $sorting--;
								$pageTree['pages']['NEW' . $pageIndex]['hidden'] = GeneralUtility::_POST('hidePages') ? 1 : 0;

									// Drop the title
								array_shift($parts);

									// Add additional field values
								if ($ef) {
									foreach ($ef as $index => $field) {
										$pageTree['pages']['NEW' . $pageIndex][$field] = $parts[$index];
									}
								}
								$oldLevel = $level;
								$pageIndex++;
							}
						}
					}

					if (count($pageTree['pages'])) {
						reset($pageTree);
						$tce = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
						$tce->stripslashes_values = 0;
							//reverseOrder does not work with nested arrays
							//$tce->reverseOrder=1;
						$tce->start($pageTree, array());
						$tce->process_datamap();
						t3lib_BEfunc::setUpdateSignal('updatePageTree');
					} else {
						$theCode .= $GLOBALS['TBE_TEMPLATE']->rfw($this->getLanguageLabel('wiz_newPageTree_noCreate') . '<br /><br />');
					}

						// Display result:
					$tree = GeneralUtility::makeInstance('TYPO3\CMS\Backend\Tree\View\BrowseTreeView');
					$tree->init(' AND pages.doktype < 199 AND pages.hidden = "0"');
					$tree->thisScript = 'index.php';
					$tree->setTreeName('pageTree');
					$tree->ext_IconMode = TRUE;
					$tree->expandAll = TRUE;
					$tree->tree[] = array(
						'row' => $thePid,
						'title' => 'blip',
						'HTML' => t3lib_iconWorks::getIconImage('pages', $thePid, $GLOBALS['BACK_PATH'], 'align="top"')
					);
					$tree->getTree($thePid);

					$theCode .= $this->getLanguageLabel('wiz_newPageTree_created');
					$theCode .= $tree->printTree();

				}
			} else {
				$theCode .= $this->displayCreatForm();
			}
		} else {
			$theCode .= $GLOBALS['TBE_TEMPLATE']->rfw($this->getLanguageLabel('wiz_newPageTree_errorMsg1'));
		}

			// Context Sensitive Help
		$theCode .= t3lib_BEfunc::cshItem('_MOD_web_func', 'tx_wizardcrpagetree', $GLOBALS['BACK_PATH'], '<br/>|');

		$out = $this->pObj->doc->section($this->getLanguageLabel('wiz_crMany'), $theCode, 0, 1);
		return $out;
	}

	/**
	 * Return the data as a compressed array
	 *
	 * @param	array		$data: the uncompressed array
	 * @return	array		the data as a compressed array
	 */
	private function compressArray($data) {
		$newData = array();
		foreach ($data as $key => $value) {
			if ($value['value']) {
				$newData[] = $value['value'];
			}
			if ($value['data']) {
				$newData = array_merge($newData, $this->compressArray($value['data']));
			}
		}
		return $newData;
	}

	/**
	 * Return the data as a nested array
	 *
	 * @param	array		$data: the data array
	 * @param	int		$oldLevel: the current level
	 * @param	string		$ic: indentation character
	 * @return	array		the data as a nested array
	 */
	private function getArray($data, $oldLevel = 0, $ic) {
		$size = count($data);
		$newData = array();
		for ($i = 0; $i < $size;) {
			$regs = array();
			$v = $data[$i];
			if (trim($v)) {
					// What level are we on?
				preg_match('/^' . $ic . '*/', $v, $regs);
				$level = strlen($regs[0]);

				if ($level > $oldLevel) {
					/**
					 * We have entered a sub level. Find the chunk of the array that
					 * constitues this sub level. Pass this chunk to the getArray
					 * function. Then increase the $i to point to the point where the
					 * level is the same as we are on now.
					 */
					$subData = array();
					for ($j = $i; $j < $size; $j++) {
						$regs = array();
						$v = $data[$j];
						if (trim($v)) {
								// What level are we on?
							preg_match('/^' . $ic . '*/', $v, $regs);
							$subLevel = strlen($regs[0]);
							if ($subLevel >= $level) {
								$subData[] = $v;
							} else {
								break;
							}
						}
					}
					$newData[$i - 1]['data'] = $this->getArray($subData, $level, $ic);
					$i = $i + count($subData);
				} elseif (($level == 0) or ($level === $oldLevel)) {
					$newData[$i]['value'] = $v;
					$i++;
				}
				$oldLevel = $level;
			}
			if ($i == $size) {
				break;
			}
		}
		return $newData;
	}

	/**
	 * Return the data with all the leaves sorted in reverse order
	 *
	 * @param	array		$data: input array
	 * @return	array		the data reversed
	 */
	private function reverseArray($data) {
		$newData = array();
		$i = 0;
		foreach ($data as $key => $chunk) {
			if (is_array($chunk['data'])) {
				$newData[$i]['data'] = $this->reverseArray($chunk['data']);
				krsort($newData[$i]['data']);
			}
			$newData[$i]['value'] = $chunk['value'];
			$i++;
		}
		krsort($newData);
		return $newData;
	}

	/**
	 * Return the data without comment fields and empty lines
	 *
	 * @param	array		$data: input array
	 * @return	array		the data reversed
	 */
	private function filterComments($data) {
		$newData = array();
		$multiLine = FALSE;
		foreach ($data as $key => $value) {

				// Multiline comment
			if (preg_match('#^/\*#', $value) and !$multiLine) {
				$multiLine = TRUE;
				continue;
			}
			if (preg_match('#[\*]+/#', ltrim($value)) and $multiLine) {
				$multiLine = FALSE;
				continue;
			}
			if ($multiLine) {
				continue;
			}
				// Single line comment
			if (preg_match('#^//#', ltrim($value)) or preg_match('/^#/', ltrim($value))) {
				continue;
			}

				// Empty line
			if (!trim($value)) {
				continue;
			}

			$newData[] = $value;
		}
		return $newData;
	}

	/**
	 * Return html to display the creation form
	 *
	 * @return	array		the data reversed
	 */
	private function displayCreatForm() {
		$form = '<b>' . $this->getLanguageLabel('wiz_newPageTree') . ':</b><p>' . $this->getLanguageLabel('wiz_newPageTree_howto') . '</p>
		' . $this->getLanguageLabel('wiz_newPageTree_indentationCharacter') . '
		<select name="indentationCharacter">
			<option value="space" selected="selected">' . $this->getLanguageLabel('wiz_newPageTree_indentationSpace') . '</option>
			<option value="tab">' . $this->getLanguageLabel('wiz_newPageTree_indentationTab') . '</option>
			<option value="dot">' . $this->getLanguageLabel('wiz_newPageTree_indentationDot') . '</option>
		</select><br/>
		<textarea name="data"' . $this->pObj->doc->formWidth(35) . ' rows="8"/></textarea>
		<br />
		<br />
		<input type="checkbox" name="createInListEnd" value="1" /> ' . $this->getLanguageLabel('wiz_newPageTree_listEnd') . '<br />
		<input type="checkbox" name="hidePages" value="1" /> ' . $this->getLanguageLabel('wiz_newPageTree_hidePages') . '<br />
		<input type="submit" name="create" value="' . $this->getLanguageLabel('wiz_newPageTree_lCreate') . '" onclick="return confirm(' . $GLOBALS['LANG']->JScharCode($this->getLanguageLabel('wiz_newPageTree_lCreate_msg1')) . ')"> <input type="reset" value="' . $this->getLanguageLabel('wiz_newPageTree_lReset') . '" />
		<br />
		<br />
		<b>' . $this->getLanguageLabel('wiz_newPageTree_advanced') . '</b><br/>
		' . $this->getLanguageLabel('wiz_newPageTree_extraFields') . '<br />
		<input type="text" name="extraFields" size="30" /><br />
		' . $this->getLanguageLabel('wiz_newPageTree_separationCharacter') . '
		<select name="separationCharacter">
			<option value="comma" selected="selected">' . $this->getLanguageLabel('wiz_newPageTree_separationComma') . '</option>
			<option value="pipe">' . $this->getLanguageLabel('wiz_newPageTree_separationPipe') . '</option>
			<option value="semicolon">' . $this->getLanguageLabel('wiz_newPageTree_separationSemicolon') . '</option>
			<option value="colon">' . $this->getLanguageLabel('wiz_newPageTree_separationColon') . '</option>
		</select><br/>
		<br/>
		<input type="hidden" name="newPageTree" value="submit"/>';
		return $form;
	}

	/**
	 * Get the indentation character (space, tab or dot)
	 *
	 * @return	string		the indentation character
	 */
	private function getIndentationChar() {
		$ic = GeneralUtility::_POST('indentationCharacter');
		switch ($ic) {
			case 'dot':
				$ic = '\.';
				break;
			case 'tab':
				$ic = '\t';
				break;
			case 'space':
			default:
				$ic = ' ';
				break;
		}
		return $ic;
	}

	/**
	 * Get the separation character (, or | or ; or :)
	 *
	 * @return	string		the separation character
	 */
	private function getSeparationChar() {
		$sc = GeneralUtility::_POST('separationCharacter');
		switch ($sc) {
			case 'pipe':
				$sc = '|';
				break;
			case 'semicolon':
				$sc = ';';
				break;
			case 'colon':
				$sc = ':';
				break;
			case 'comma':
			default:
				$sc = ',';
				break;
		}
		return $sc;
	}

	/**
	 * Get the extra fields
	 *
	 * @return	array		the extra fields
	 */
	private function getExtraFields() {
		$efLine = GeneralUtility::_POST('extraFields');
		if (trim($efLine)) {
			$ef = GeneralUtility::trimExplode(' ', $efLine, 1);
			return $ef;
		}
		return FALSE;
	}

	/**
	 * Get Language Label
	 *
	 * @param	string		$label
	 * @return	string		The translated string
	 */
	private function getLanguageLabel($label) {
		return $GLOBALS['LANG']->sL('LLL:EXT:wizard_crpagetree/Resources/Private/Language/locallang.xml:' . $label);
	}

	/**
	 * Return the helpbubble image tag.
	 *
	 * @return	string		HTML code for a help-bubble image.
	 */
	public function helpBubble() {
		return '<img src="' . $GLOBALS['BACK_PATH'] . 'gfx/helpbubble.gif" width="14" height="14" hspace="2" align="top"' . $this->pObj->doc->helpStyle() . ' alt="" />';
	}
}

?>
