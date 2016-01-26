<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/factor.lib.php
 *	\ingroup	factor
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function factorAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("factor@factor");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/factor/admin/factor_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/factor/admin/factor_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@factor:/factor/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@factor:/factor/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'factor');

    return $head;
}

function _export_factures(&$db, &$format, &$TRefFacture)
{
	switch ($format) {
		case 'natixis':
			$fileName = _parseNatixis($db, $TRefFacture);
			break;
	}
	
	if ($fileName)
	{
		header('Location: '.dol_buildpath('document.php?modulepart=factor&file='.$fileName, 2));
		exit;
	}
	
}

function _parseNatixis(&$db, &$TRefFacture)
{
	global $conf,$langs;
	
	$TError = $TData = array();
	$currency = str_pad($conf->currency, 3);
	$cptLine = 2; //cpt à 2 pcq firstLine contient déjà le cpt à 1
	$firstLine = array('01000001138FA053506'.$currency.date('Ymd'));
	$total = 0;
	
	foreach ($TRefFacture as $ref)
	{
		$facture = new Facture($db);
		$facture->fetch('', $ref);
		$facture->fetch_thirdparty();
		
		$facnumber = '037510';
		if (empty($facture->thirdparty->code_compta)) $TError['TErrorCodeCompta'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyCodeCompta', $facture->ref, $facture->thirdparty->name);
		if (empty($facture->mode_reglement_code)) $TError['TErrorModeReglt'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyModeReglt', $facture->ref);
		
		$TData[] = array(
			'04'
			,str_pad($cptLine, 6, 0, STR_PAD_LEFT)
			,'138FA053506'
			,$currency
			,str_pad($facnumber, 8) //Num facture
			,$facture->thirdparty->code_compta
			,str_repeat(' ', 6)
			,date('Ymd',$facture->date)
			,date('Ymd', $facture->date_lim_reglement)
			,str_pad($facture->mode_reglement_code, 3)
			,str_repeat(' ', 66)
			,str_pad($facture->total_ttc*100, 30, 0, STR_PAD_LEFT)
		);
		
		$total += $facture->total_ttc*100;
		$cptLine++;
	}
	
	$_SESSION['TErrorCodeCompta'] = $TError['TErrorCodeCompta'];
	$_SESSION['TErrorModeReglt'] = $TError['TErrorModeReglt'];
	
	$cptLine = str_pad($cptLine, 6, 0, STR_PAD_LEFT);
	$total = str_pad($total, 30, 0, STR_PAD_LEFT);
	$endLine = array('09'.$cptLine.'138FA053506'.$currency.str_repeat(' ', 108).$total);
	
	// write file
	$folder = ($conf->entity == 1) ? DOL_DATA_ROOT.'/factor/' : DOL_DATA_ROOT.'/'.$conf->entity.'/factor/';
	dol_mkdir($folder);
	
	$fileName = 'export_natixis_'.date('Ymd').'.txt';
	$fullPath = $folder.$fileName;
	
	$handle = fopen($fullPath, 'w');
	
	if ($handle)
	{
		fwrite($handle, implode('', $firstLine)."\n");
		foreach ($TData as &$TInfo)
		{
			fwrite($handle, implode('', $TInfo)."\n");
		}
		fwrite($handle, implode('', $endLine)."\n");
		fclose($handle);
		
		return $fileName;
	}
	else {
		setEventMessages('ErrorCanNotCreateExportFile', array(), 'errors');
		return 0;
	}
}
