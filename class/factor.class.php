<?php

class TFactor extends TObjetStd {
	
	function __construct() { /* declaration */
               global $conf;
                
               parent::set_table(MAIN_DB_PREFIX.'factor');
               parent::add_champs('fk_soc,fk_bank_account',array('type'=>'int', 'index'=>true));                              //type de valideur
               parent::_init_vars('mention');
			   
               parent::start();
	}

	static function getAll(&$PDOdb) {
		
		$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."factor WHERE 1 ORDER BY rowid");
		
		$TFactor = array();
		foreach($Tab as $row) {
			
			$TFactor[]  = $row->rowid;
			
		}
		
		return $TFactor;
		
	}
	
	static function getBankFromSoc(&$PDOdb, $fk_soc) {
		
		$factor = new TFactor;
		if($factor->loadBy($PDOdb, $fk_soc, 'fk_soc', false)) {
			return $factor->fk_bank_account;
			
		}
		else {
			return 0;
		}
		
	}
	
	static function addEvent($fk_facture, $ref='') {
		global $db,$langs,$user;
		
		dol_include_once('/comm/action/class/actioncomm.class.php');
		
		$a=new ActionComm($db);
		$a->type_code = 'AC_OTH_AUTO';
		$a->label = $langs->trans('BillClassifyDeposed',$ref);
		$a->fk_element = $fk_facture;
		$a->elementtype = 'facture';
		$a->usertodo = $user;
		$a->userdone = $user;
		$a->percentage = 100;
		$a->datep = date('Y-m-d H:i:s');
		$a->add($user);
		
	}
	 
}
