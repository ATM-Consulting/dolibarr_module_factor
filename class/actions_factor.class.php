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
 * \file    class/actions_factor.class.php
 * \ingroup factor
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsFactor
 */
class ActionsFactor
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	function doActions($parameters, &$object, &$action, $hookmanager) 
	{	
	}

	function beforePDFCreation($parameters, &$object, &$action, $hookmanager) 
	{
		if ($object->element == 'facture')
		{
			if (isset($object->thirdparty)) $societe = &$object->thirdparty;
			else 
			{
				dol_include_once('/societe/class/societe.class.php');
				$societe = new Societe($db);
				$societe->fetch($object->socid);
			}
			
			if(!empty($societe->id)) 
			{
				global $db,$conf;
				
				if(!empty($societe->array_options['options_fk_soc_factor']) && $societe->array_options['options_factor_suivi'] == 1) 
				{
					define('INC_FROM_DOLIBARR', true);
					dol_include_once('/factor/config.php');
					dol_include_once('/factor/class/factor.class.php');
					
					$PDOdb = new TPDOdb;
					
					$factor = new TFactor;
					$factor->loadBy($PDOdb, $societe->array_options['options_fk_soc_factor'], 'fk_soc');
					
					if(!empty($factor->mention)) 
					{
						if($conf->global->FACTOR_PDF_DISPOSITION == 'public_note') {
							if(strpos($object->note_public, $factor->mention) === false) 
							{
								$object->note_public = $factor->mention.(!empty($object->note_public) ? "\n\n".$object->note_public : '');
								$r=$object->update_note($object->note_public, '_public');
							}
						}
						
						if($conf->global->FACTOR_PDF_DISPOSITION == 'footer') {
							$conf->global->INVOICE_FREE_TEXT = $factor->mention . $conf->global->INVOICE_FREE_TEXT;
						}
					}
				}
			}
			
		}
		
	}
}