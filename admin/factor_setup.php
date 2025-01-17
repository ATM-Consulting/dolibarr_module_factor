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

$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];

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
	if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
	{
		if ($code=='FACTOR_CAN_USE_CUSTOMER') {

			$val=GETPOST($code, 'none');
			if (!$val) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX .'extrafields SET param=\'a:1:{s:7:"options";a:1:{s:32:"societe:nom:rowid::fournisseur=1";N;}}\' WHERE elementtype=\'societe\' AND name=\'fk_soc_factor\'';
				$res = $db->query($sql);
			} else {
				$sql = 'UPDATE '.MAIN_DB_PREFIX .'extrafields SET param=\'a:1:{s:7:"options";a:1:{s:19:"societe:nom:rowid::";N;}}\' WHERE elementtype=\'societe\' AND name=\'fk_soc_factor\'';
				$res = $db->query($sql);
			}
			if (!$res) {
				setEventMessage($db->lasterror,'errors');
			}
		}

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
	if (dolibarr_del_const($db, $code, $conf->entity) > 0)
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
	$factor->load($PDOdb, GETPOST('id', 'int'));
	$factor->delete($PDOdb);
}
else if($action == 'save') {

	if(GETPOST('bt_add', 'none')!='') {

		$factor = new TFactor; // nouveau factor
		$factor->entity = $conf->entity;
		$factor->save($PDOdb);

	}
	else {
		$TFactor = GETPOST('TFactor', 'array');
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
print load_fiche_titre($langs->trans($page_name), $linkback, "object_factor.svg@factor");

// Configuration header
$head = factorAdminPrepareHead();
print dol_get_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104905Name"),
    0,
    "factor@factor"
);

print dol_get_fiche_end();
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


	echo '<td>'.$form->select_thirdparty_list($factor->fk_soc,'TFactor['.$factor->getId().'][fk_soc]',(!getDolGlobalString('FACTOR_CAN_USE_CUSTOMER')?'fournisseur=1':''))
	.'<br />'
	.$selectBank
	.'</td>'; // supplier

	if(isModEnabled("fckeditor")) {
	$editor=new DolEditor('TFactor['.$factor->getId().'][mention]',$factor->mention,'',200);
    	echo '<td>'.$editor->Create(1).'</td>';
	} else {
		echo '<td>'.$formCore->zonetexte('', 'TFactor['.$factor->getId().'][mention]', $factor->mention, 80,5).'</td>';
	}

	echo '<td><a href="?action=delete_factor&token=' . $newToken . '&id='.$factor->getId().'">'.img_delete( $langs->trans('Delete') ).'</a></td>';

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
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_FACTOR_LIMIT_DEPOT">';
print $formCore->texte('',"FACTOR_LIMIT_DEPOT",  getDolGlobalString('FACTOR_LIMIT_DEPOT') ,3);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FACTOR_DO_NOT_UPDATE_NOTE_ON_PROPAL").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print ajax_constantonoff('FACTOR_DO_NOT_UPDATE_NOTE_ON_PROPAL');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FACTOR_CAN_USE_CUSTOMER").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_FACTOR_CAN_USE_CUSTOMER">';
print $form->selectarray('FACTOR_CAN_USE_CUSTOMER',array('0'=>'Non','1'=>'Oui'),getDolGlobalString('FACTOR_CAN_USE_CUSTOMER') ? 1 : 0);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Note publique ou pied de page
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PDFMentionDisposition").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_FACTOR_PDF_DISPOSITION">';
print $form->selectarray('FACTOR_PDF_DISPOSITION', array('public_note' => $langs->trans("PublicNote"),'footer' => $langs->trans("Footer")), getDolGlobalString('FACTOR_PDF_DISPOSITION'));
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '</table>';

llxFooter();

$db->close();
