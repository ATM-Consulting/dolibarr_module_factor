<?php

class TFactor extends TObjetStd {

    public $element = "factor";

	function __construct() { /* declaration */
               global $conf;
                
               parent::set_table(MAIN_DB_PREFIX.'factor');
               parent::add_champs('fk_soc,fk_bank_account',array('type'=>'int', 'index'=>true));                              //type de valideur
               parent::add_champs('entity',array('type'=>'int', 'default'=>1, 'index'=>false));
               parent::add_champs('mention',array('type'=>'text'));
               parent::_init_vars();
			   
               parent::start();
	}

	static function getAll(&$PDOdb) {
		
		global $conf;
		
		$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."factor WHERE 1 AND entity = ".$conf->entity." ORDER BY rowid");
		
		$TFactor = array();
		foreach($Tab as $row) {
			
			$TFactor[]  = $row->rowid;
			
		}
		
		return $TFactor;
		
	}
	
	static function getBankFromSoc(&$PDOdb, $fk_soc) {
		
		global $conf;

		$factor = new TFactor;
		if ($result = $factor->LoadAllBy($PDOdb, array('fk_soc'=>$fk_soc, 'entity'=>$conf->entity), false)) {

			$factor = reset($result);	// Take first record found

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
		if((float) DOL_VERSION >= 11) $a->create($user);
		else $a->add($user);
		
	}
	 
}
