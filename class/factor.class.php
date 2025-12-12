<?php
/* Copyright (C) 2025 ATM Consulting
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
include_once __DIR__.'/../config.php';

/**
 * Class TFactor
 *
 * Mapping table llx_factor.
 */
class TFactor extends TObjetStd
{

	public $element = "factor";

	/**
	 * Constructor
	 */
	public function __construct()
	{
		/* declaration */
		global $conf;

		parent::set_table(MAIN_DB_PREFIX.'factor');
		parent::add_champs('fk_soc,fk_bank_account', array('type'=>'int', 'index'=>true));                              //type de valideur
		parent::add_champs('entity', array('type'=>'int', 'default'=>1, 'index'=>false));
		parent::add_champs('mention', array('type'=>'text'));
		parent::_init_vars();

		parent::start();
	}

	/**
	 * Return all factor rowids for current entity.
	 *
	 * @param TPDOdb $PDOdb Database handler
	 * @return int[]        List of rowids
	 */
	public static function getAll(&$PDOdb)
	{

		global $conf;

		$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."factor WHERE 1 AND entity = ".$conf->entity." ORDER BY rowid");

		$TFactor = array();
		foreach ($Tab as $row) {
			$TFactor[]  = $row->rowid;
		}

		return $TFactor;
	}

	/**
	 * Get bank account id linked to a thirdparty for current entity.
	 *
	 * @param TPDOdb $PDOdb  Database handler
	 * @param int    $fk_soc Thirdparty id
	 * @return int           Bank account id, or 0 if not found
	 */
	public static function getBankFromSoc(&$PDOdb, $fk_soc)
	{

		global $conf;

		$factor = new TFactor;
		if ($result = $factor->LoadAllBy($PDOdb, array('fk_soc'=>$fk_soc, 'entity'=>$conf->entity), false)) {
			$factor = reset($result);	// Take first record found

			return $factor->fk_bank_account;
		} else {
			return 0;
		}
	}

	/**
	 * Add an automatic event on invoice.
	 *
	 * @param int    $fk_facture Invoice id
	 * @param string $ref        Invoice ref (optional)
	 * @return void
	 */
	public static function addEvent($fk_facture, $ref = '')
	{
		global $db,$langs,$user;

		dol_include_once('/comm/action/class/actioncomm.class.php');

		$a=new ActionComm($db);
		$a->type_code = 'AC_OTH_AUTO';
		$a->label = $langs->trans('BillClassifyDeposed', $ref);
		$a->fk_element = $fk_facture;
		$a->elementtype = 'facture';
		$a->usertodo = $user;
		$a->userdone = $user;
		$a->percentage = 100;
		$a->datep = date('Y-m-d H:i:s');
		if ((float) DOL_VERSION >= 11) $a->create($user);
		else $a->add($user);
	}
}
