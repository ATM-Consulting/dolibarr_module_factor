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
	 
}
