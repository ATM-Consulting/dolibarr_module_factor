<?php

class TFactor extends TObjetStd {
	
	function __construct() { /* declaration */
               global $conf;
                
               parent::set_table(MAIN_DB_PREFIX.'factor');
               parent::add_champs('fk_soc,fk_bank_account',array('type'=>'int', 'index'=>true));                              //type de valideur
               parent::add_champs('mention',array('type'=>'text'));
               parent::_init_vars();
			   
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
	
	static function getAccountFromSoc(&$PDOdb, $fk_soc) {
		global $db,$conf,$user,$langs;
		dol_include_once('/societe/class/societe.class.php');
		dol_include_once('/compta/bank/class/account.class.php');
		dol_include_once('/societe/class/companybankaccount.class.php');
		$societe = new Societe($db);
		
		if($societe->fetch($fk_soc)>0) {
			
			if(!empty($societe->array_options['options_fk_soc_factor']) && $societe->array_options['options_factor_suivi'] == 1) 
			{
				$factor = new TFactor;
				$factor->loadBy($PDOdb, $societe->array_options['options_fk_soc_factor'], 'fk_soc');
				if($factor->fk_bank_account>0) {
					
					$account = new Account($db);
					$account->fetch($factor->fk_bank_account);
					
					return $account;
				}
				else{
					
					$supplier=new Societe($db);
					if($supplier->fetch($factor->fk_soc)>0) {
						
						$name_rib_to_find = $supplier->name;
						
						$TRib = $societe->get_all_rib();
						foreach($TRib as &$rib) {
							
							if(strcasecmp( $rib->label, $name_rib_to_find) == 0){
									
								return $rib;
							}
							
						}
						
					}
					
				}
				
			}		
		}

		$account = new Account($db);
		
		return $account;
		
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
