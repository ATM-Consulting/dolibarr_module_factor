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
 * 	\file		core/triggers/interface_99_modMyodule_Factortrigger.class.php
 * 	\ingroup	factor
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceFactortrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'factor@factor';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, &$object, $user, $langs, $conf)
    {
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users

	   	if ($action === 'BILL_CREATE')
	   	{
	   		$this->setFkAccountIfIsFactor($object);
	   	}
		else if($action==='PROPAL_CREATE' && !empty($conf->global->BANK_ASK_PAYMENT_BANK_DURING_PROPOSAL) ) {
			$this->setFkAccountIfIsFactor($object);
		}


        return 1;
    }

    /**
     * Set the field bank account automatically according to the factor of thirdparty and setup
     *
     * @param 	Object		$object		Object edited
     * @return 	boolean
     */
	public function setFkAccountIfIsFactor(&$object)
	{
		global $db, $conf;

		if (!isset($object->thirdparty)) $object->fetch_thirdparty();

		if (empty($object->thirdparty->id)) return false;

		if(!empty($object->thirdparty->array_options['options_fk_soc_factor']) && $object->thirdparty->array_options['options_factor_suivi'] == 1)
		{
			if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
			dol_include_once('/factor/config.php');
			dol_include_once('/factor/class/factor.class.php');

			$PDOdb = new TPDOdb;

			$factor = new TFactor;
			$result = $factor->LoadAllBy($PDOdb, array('fk_soc'=> $object->thirdparty->array_options['options_fk_soc_factor'], 'entity'=>$conf->entity));

			$factor = reset($result);	// Take first answer found

			if(!empty($factor->mention) && !empty($factor->fk_bank_account))
			{
				if(strpos($object->note_public, $factor->mention) === false)
				{
					$note = $factor->mention.(!empty($facture->note_public) ? "\n\n".$facture->note_public : '');
					if ($this->checkCanUpdateNote($object)) $object->update_note($note, '_public');
					$object->setBankAccount($factor->fk_bank_account);
				}
			}

		}
	}

	private function checkCanUpdateNote($object)
	{
		global $conf;

		if ($object->element == 'propal' && !empty($conf->global->FACTOR_DO_NOT_UPDATE_NOTE_ON_PROPAL)) return false;

		return true;
	}
}