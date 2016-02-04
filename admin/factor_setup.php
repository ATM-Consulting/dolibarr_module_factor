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
 * 	\file		admin/factor.php
 * 	\ingroup	factor
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
require('../config.php');

dol_include_once('/factor/class/factor.class.php');

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once '../lib/factor.lib.php';

// Translations
$langs->load("factor@factor");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	
if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

$PDOdb=new TPDOdb;
if($action=='delete_factor') {
	$factor = new TFactor; // nouveau factor
	$factor->load($PDOdb, GETPOST('id'));
	$factor->delete($PDOdb);
}
else if($action == 'save') {
	
	if(GETPOST('bt_add')!='') {
		
		$factor = new TFactor; // nouveau factor
		$factor->save($PDOdb);
		
	}
	else {
		$TFactor = GETPOST('TFactor');
		if(!empty($TFactor)) {
			foreach($TFactor as $id => &$dataFactor) {
				
				$factor =new TFactor;
				$factor->load($PDOdb, $id);
				$factor->set_values($dataFactor);
				$factor->save($PDOdb);
			}	
		}
	}
}

/*
 * View
 * Sélection d'un fournisseur et ajout de la mention de subrogation ainsi que du compte à mettre par défaut sur la facture au factor
 */
$page_name = "FactorSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = factorAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104905Name"),
    0,
    "factor@factor"
);
$form=new Form($db);

$TFactor = TFactor::getAll($PDOdb);
// Setup page goes here
$formCore = new TFormCore('auto','form1','post');
echo $formCore->hidden('action', 'save');
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Supplier/BankAccount").'</td>'."\n";
print '<td>'.$langs->trans("Mention").'</td>'."\n";
print '<td>&nbsp;</td>'."\n";
print '</tr>';

foreach($TFactor as $idFactor) {
	
	$factor = new TFactor;
	$factor->load($PDOdb, $idFactor);
	
	// Example with a yes / no select
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	
	ob_start();
	$form->select_comptes($factor->fk_bank_account,'TFactor['.$factor->getId().'][fk_bank_account]');
	$selectBank = ob_get_clean();
	
	
	echo '<td>'.$form->select_thirdparty_list($factor->fk_soc,'TFactor['.$factor->getId().'][fk_soc]','fournisseur=1')
	.'<br />'
	.$selectBank
	.'</td>'; // supplier
	
	if(!empty($conf->fckeditor->enabled)) {
	$editor=new DolEditor('TFactor['.$factor->getId().'][mention]',$factor->mention,'',200);
    	echo '<td>'.$editor->Create(1).'<td>';
	} else {
		echo '<td>'.$formCore->zonetexte('', 'TFactor['.$factor->getId().'][mention]', $factor->mention, 80,5).'</td>';	
	}

	echo '<td><a href="?action=delete_factor&id='.$factor->getId().'">'.img_delete( $langs->trans('Delete') ).'</a></td>';
	
	print '</tr>';

}
print '</table><div class="tabsAction">';

echo $formCore->btsubmit($langs->trans('Add'), 'bt_add','','butAction');
echo $formCore->btsubmit($langs->trans('Save'), 'bt_save','','butAction');

echo '</div>';

$formCore->end();

// Setup page goes here

$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";


// Example with a yes / no select
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("setFACTOR_LIMIT_DEPOT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FACTOR_LIMIT_DEPOT">';
print $formCore->texte('',"FACTOR_LIMIT_DEPOT",$conf->global->FACTOR_LIMIT_DEPOT,3);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '</table>';

llxFooter();

$db->close();