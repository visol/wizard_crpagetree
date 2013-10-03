<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007 Michiel Roos <extensions@netcreators.com>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Contains class for "Create page tree" wizard
 *
 * $Id$
 *
 * @author	Michiel Roos <extensions@netcreators.com>
 *
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   66: class tx_wizardcrpagetree_webfunc extends t3lib_extobjbase
 *   72:     function main()
 *  203:     function compressArray($data)
 *  222:     function getArray($data, $oldLevel = 0, $ic)
 *  274:     function reverseArray($data)
 *  295:     function filterComments($data)
 *  330:     function displayCreatForm()
 *  367:     function getIndentationChar()
 *  389:     function getSeparationChar()
 *  414:     function getExtraFields()
 *  428:     function helpBubble()
 *
 * TOTAL FUNCTIONS: 10
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(PATH_t3lib.'class.t3lib_pagetree.php');
require_once(PATH_t3lib.'class.t3lib_page.php');
require_once(PATH_t3lib.'class.t3lib_tcemain.php');
require_once(PATH_t3lib.'class.t3lib_extobjbase.php');

/**
 * Creates the "Create pagetree" wizard
 *
 * @author	Michiel Roos <extensions@netcreators.com>
 * @package TYPO3
 * @subpackage tx_wizardcrpagetree
 */
class tx_wizardcrpagetree_webfunc extends t3lib_extobjbase {
	/**
 * Main function creating the content for the module.
 *
 * @return	string		HTML content for the module, actually a "section" made through the parent object in $this->pObj
 */
	function main()	{
		global $LANG;

		$theCode='';
		// create new pages here?
		$pRec = t3lib_BEfunc::getRecord ('pages',$this->pObj->id,'uid',' AND '.$GLOBALS['BE_USER']->getPagePermsClause(8));
		$sys_pages = t3lib_div::makeInstance('t3lib_pageSelect');
		$menuItems = $sys_pages->getMenu($this->pObj->id);
		if (is_array($pRec))	{
			if (t3lib_div::_POST('newPageTree') === 'submit') {
				$data = explode("\r\n", t3lib_div::_POST('data'));
				$data = $this->filterComments($data);
				if (count($data)) {
					if (t3lib_div::_POST('createInListEnd')) {
						$endI = end($menuItems);
						$thePid = -intval($endI['uid']);
						if (!$thePid)	$thePid = $this->pObj->id;
					} else {
						// get parent pid
						$thePid = $this->pObj->id;
					}

					$ic = $this->getIndentationChar();
					$sc = $this->getSeparationChar();
					$ef = $this->getExtraFields();

					// Reverse the ordering of the data
					$originalData = $this->getArray($data,0,$ic);
					$reversedData = $this->reverseArray($originalData);
					$data = $this->compressArray($reversedData);
					//$data = $this->compressArray($originalData);

					if ($data) {
						$pageIndex = count($data);
						$sorting = count($data);
						$oldLevel = 0;
						$parentPid = array();
						while(list($k,$line)=each($data))	{
							if (trim($line))	{
								// What level are we on?
								ereg("^$ic*", $line, $regs);
								$level = strlen($regs[0]);

								if ($level == 0) {
									$currentPid = $thePid;
									$parentPid[$level] = $thePid;
								}
								elseif ($level > $oldLevel) {
									$currentPid = 'NEW'.($pageIndex-1);
									$parentPid[$level] = $pageIndex-1;
								}
								elseif ($level === $oldLevel) {
									$currentPid = 'NEW'.$parentPid[$level];
								}
								elseif ($level < $oldLevel) {
									$currentPid = 'NEW'.$parentPid[$level];
								}

								// Get title and additional field values
								$parts = t3lib_div::trimExplode($sc, $line);

								$pageTree['pages']['NEW'.$pageIndex]['title'] = ltrim($parts[0],$ic);
								$pageTree['pages']['NEW'.$pageIndex]['pid'] = $currentPid;
								$pageTree['pages']['NEW'.$pageIndex]['sorting'] = $sorting--;
								$pageTree['pages']['NEW'.$pageIndex]['hidden'] = t3lib_div::_POST('hidePages') ? 1 : 0;

								// Drop the title
								array_shift($parts);

								// Add additional field values
								if ($ef)
									foreach ($ef as $index => $field)
										$pageTree['pages']['NEW'.$pageIndex][$field]=$parts[$index];

								$oldLevel = $level;
								$pageIndex++;
							}
						}
					}

					if (count($pageTree['pages']))	{
						reset($pageTree);
						$tce = t3lib_div::makeInstance('t3lib_TCEmain');
						$tce->stripslashes_values=0;
						//reverseOrder does not work with nested arrays
						//$tce->reverseOrder=1;
						$tce->start($pageTree,array());
						$tce->process_datamap();
						t3lib_BEfunc::getSetUpdateSignal('updatePageTree');
					} else {
						$theCode.=$GLOBALS['TBE_TEMPLATE']->rfw($LANG->getLL('wiz_newPageTree_noCreate').'<br /><br />');
					}

					// Display result:
					$tree = t3lib_div::makeInstance('t3lib_browseTree');
					$tree->init(' AND pages.doktype < 199 AND pages.hidden = "0"');
					$tree->thisScript = 'index.php';
					$tree->setTreeName('pageTree');
					$tree->ext_IconMode = true;
					$tree->expandAll = true;
					$tree->tree[] = array(
						'row' => $thePid,
						'title' => 'blip',
						'HTML' => t3lib_iconWorks::getIconImage('pages', $thePid, $GLOBALS['BACK_PATH'],'align="top"')
					);
					$tree->getTree($thePid);

					$theCode .= $LANG->getLL('wiz_newPageTree_created');
					$theCode .= $tree->printTree();

				}
			} else {
				$theCode .= $this->displayCreatForm();
			}
		} else {
			$theCode.=$GLOBALS['TBE_TEMPLATE']->rfw($LANG->getLL('wiz_newPageTree_errorMsg1'));
		}

		// Context Sensitive Help
		$theCode.= t3lib_BEfunc::cshItem('_MOD_web_func', 'tx_wizardcrpagetree', $GLOBALS['BACK_PATH'],'<br/>|');

		$out=$this->pObj->doc->section($LANG->getLL('wiz_crMany'),$theCode,0,1);
		return $out;
	}

	/**
	 * Return the data as a compressed array
	 *
	 * @param	array		$data: the uncompressed array
	 * @return	array		the data as a compressed array
	 */
	function compressArray($data)	{
		$newData = array();
		foreach ($data as $key => $value) {
			if ($value['value'])
				$newData[] = $value['value'];
			if ($value['data'])
				$newData = array_merge ($newData,$this->compressArray($value['data']));
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
	function getArray($data, $oldLevel = 0, $ic)	{
		$size = count($data);
		$newData = array();
		for($i = 0; $i < $size;)	{
			$regs = array ();
			$v = $data[$i];
			if (trim($v))	{
				// What level are we on?
				ereg("^$ic*", $v, $regs);
				$level = strlen($regs[0]);

				if ($level > $oldLevel) {
					// We have entered a sub level. Find the chunk of the array that
					// constitues this sub level. Pass this chunk to the getArray
					// function. Then increase the $i to point to the point where the
					// level is the same as we are on now.
					$subData = array ();
					for($j = $i; $j < $size; $j++) {
						$regs = array ();
						$v = $data[$j];
						if (trim($v))	{
							// What level are we on?
							ereg("^$ic*", $v, $regs);
							$subLevel = strlen($regs[0]);
							if ($subLevel >= $level) {
								$subData[] = $v;
							}
							else
								break;
						}
					}
					$newData[$i-1]['data'] = $this->getArray($subData, $level, $ic);
					$i = $i + count($subData);
				}
				elseif (($level == 0) or ($level === $oldLevel)) {
					$newData[$i]['value'] = $v;
					$i++;
				}
				$oldLevel = $level;
			}
			if ($i == $size)
				break;
		}
		return $newData;
	}

	/**
	 * Return the data with all the leaves sorted in reverse order
	 *
	 * @param	array		$data: input array
	 * @return	array		the data reversed
	 */
	function reverseArray($data)	{
		$newData = array();
		$i = 0;
		foreach($data as $key => $chunk)	{
			if (is_array($chunk['data'])) {
				$newData[$i]['data'] = $this->reverseArray($chunk['data']);
				krsort($newData[$i]['data']);
			}
			$newData[$i]['value'] = $chunk['value'];
			$i++;
		}
		krsort ($newData);
		return $newData;
	}

	/**
	 * Return the data without comment fields and empty lines
	 *
	 * @param	array		$data: input array
	 * @return	array		the data reversed
	 */
	function filterComments($data) {
		$newData = array();
		$multiLine = false;
		foreach($data as $key => $value) {

			// Multiline comment
			if (ereg("^/\*", $value) and !$multiLine) {
				$multiLine = true;
				continue;
			}
			if (ereg("[\*]+/", ltrim($value)) and $multiLine) {
				$multiLine = false;
				continue;
			}
			if ($multiLine)
				continue;

			// Single line comment
			if (ereg("^//", ltrim($value)) or ereg("^#", ltrim($value)))
				continue;

			// Empty line
			if (!trim($value))
				continue;

			$newData[] = $value;
		}
		return $newData;
	}

	/**
	 * Return html to display the creation form
	 *
	 * @return	array		the data reversed
	 */
	function displayCreatForm()	{
		global $LANG;
		$form = '<b>'.$LANG->getLL('wiz_newPageTree').':</b><p>'.$LANG->getLL('wiz_newPageTree_howto').'</p>
		'.$LANG->getLL('wiz_newPageTree_indentationCharacter').'
		<select name="indentationCharacter">
			<option value="space" selected="selected">'.$LANG->getLL('wiz_newPageTree_indentationSpace').'</option>
			<option value="tab">'.$LANG->getLL('wiz_newPageTree_indentationTab').'</option>
			<option value="dot">'.$LANG->getLL('wiz_newPageTree_indentationDot').'</option>
		</select><br/>
		<textarea name="data"'.$this->pObj->doc->formWidth(35).' rows="8"/></textarea>
		<br />
		<br />
		<input type="checkbox" name="createInListEnd" value="1" /> '.$LANG->getLL('wiz_newPageTree_listEnd').'<br />
		<input type="checkbox" name="hidePages" value="1" /> '.$LANG->getLL('wiz_newPageTree_hidePages').'<br />
		<input type="submit" name="create" value="'.$LANG->getLL('wiz_newPageTree_lCreate').'" onclick="return confirm('.$GLOBALS['LANG']->JScharCode($GLOBALS['LANG']->getLL('wiz_newPageTree_lCreate_msg1')).')"> <input type="reset" value="'.$LANG->getLL('wiz_newPageTree_lReset').'" />
		<br />
		<br />
		<b>'.$LANG->getLL('wiz_newPageTree_advanced').'</b><br/>
		'.$LANG->getLL('wiz_newPageTree_extraFields').'<br />
		<input type="text" name="extraFields" size="30" /><br />
		'.$LANG->getLL('wiz_newPageTree_separationCharacter').'
		<select name="separationCharacter">
			<option value="comma" selected="selected">'.$LANG->getLL('wiz_newPageTree_separationComma').'</option>
			<option value="pipe">'.$LANG->getLL('wiz_newPageTree_separationPipe').'</option>
			<option value="semicolon">'.$LANG->getLL('wiz_newPageTree_separationSemicolon').'</option>
			<option value="colon">'.$LANG->getLL('wiz_newPageTree_separationColon').'</option>
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
	function getIndentationChar()	{
		$ic = t3lib_div::_POST('indentationCharacter');
		switch ($ic) {
			case 'dot':
				$ic = "\.";
				break;
			case 'tab':
				$ic = "\t";
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
	function getSeparationChar()	{
		$sc = t3lib_div::_POST('separationCharacter');
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
	function getExtraFields()	{
		$efLine = t3lib_div::_POST('extraFields');
		if (trim($efLine)) {
			$ef = t3lib_div::trimExplode(' ', $efLine, 1);
			return $ef;
		}
		return false;
	}

	/**
	 * Return the helpbubble image tag.
	 *
	 * @return	string		HTML code for a help-bubble image.
	 */
	function helpBubble()	{
		return '<img src="'.$GLOBALS['BACK_PATH'].'gfx/helpbubble.gif" width="14" height="14" hspace="2" align="top"'.$this->pObj->doc->helpStyle().' alt="" />';
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wizard_crpagetree/class.tx_wizardcrpagetree_webfunc.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wizard_crpagetree/class.tx_wizardcrpagetree_webfunc.php']);
}
?>