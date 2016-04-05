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
	
	// Première ligne du fichier
	$firstLine = '01000001138FA053506'.$currency.date('Ymd');
	$firstLine.= str_repeat(' ', 22).'D';
	$firstLine.= str_repeat(' ', 77);
	$firstLine.= str_repeat('0', 30);
	$firstLine = array($firstLine);
	$total = 0;
	
	foreach ($TRefFacture as $ref)
	{
		$facture = new Facture($db);
		$facture->fetch('', $ref);
		$facture->fetch_thirdparty();
		
		$facnumber = str_replace('-', '', $facture->ref);
		if (empty($facture->thirdparty->code_compta)) $TError['TErrorCodeCompta'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyCodeCompta', $facture->ref, $facture->thirdparty->name);
		if (empty($facture->mode_reglement_code)) $TError['TErrorModeReglt'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyModeReglt', $facture->ref);
		
		if($facture->type == 2) {
			$factype = 'AV';
			$date_ech = '        ';
			$mod = '   ';
		} else {
			$factype = 'FA';
			$date_ech = date('Ymd', $facture->date_lim_reglement);
			$mod = $facture->mode_reglement_code;
		}
		
		$TData[] = array( // Voir documentation Natixis dans répertoire Eprolor
			'04'															// Type de ligne
			,str_pad($cptLine, 6, 0, STR_PAD_LEFT)							// Compteur
			,'138'															// Ref Natixis
			,$factype														// Facture ou avoir
			,'053506'														// Ref Eprolor chez Natixis
			,$currency														// Devise
			,'0'
			,substr($facnumber, -7)				 							// Numéro de facture
			,str_pad($facture->thirdparty->code_compta,10)					// Code comptable
			,str_repeat(' ', 5)
			,date('Ymd', $facture->date)									// Date facture
			,$date_ech														// Date échéance
			,$mod															// Mode règlement
			,str_repeat(' ', 66)
			,str_repeat('0', 15)
			,str_pad(round($facture->total_ttc*100), 15, 0, STR_PAD_LEFT)			// Montant
		);
		
		$total += round($facture->total_ttc*100);
		$cptLine++;
	}
	
	$_SESSION['TErrorCodeCompta'] = $TError['TErrorCodeCompta'];
	$_SESSION['TErrorModeReglt'] = $TError['TErrorModeReglt'];
	
	$cptLine = str_pad($cptLine, 6, 0, STR_PAD_LEFT);
	
	// Dernière ligne du fichier
	$endLine = '09'.$cptLine.'138FA053506'.$currency.str_repeat(' ', 108);
	$endLine.= str_pad(count($TData), 15, 0, STR_PAD_LEFT);
	$endLine.= str_pad($total, 15, 0, STR_PAD_LEFT);
	$endLine = array($endLine);
	
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
