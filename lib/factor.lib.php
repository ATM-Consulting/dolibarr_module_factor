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
require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
//require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/PhpSpreadSheet/IOFactory.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Shared\Date;
//use PhpOffice\PhpSpreadsheet\Collection;
use Psr\SimpleCache\CacheInterface;

function factorAdminPrepareHead()
{
    global $langs, $conf;

    $object = new stdClass();

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
		case 'tif_excel':
			$fileName= _parseTifViaExcel($db, $TRefFacture);
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
			if(property_exists($facture, 'date_lim_reglement')) $date_echeance = $facture->date_lim_reglement;
			if(property_exists($facture, 'date_echeance')) $date_echeance = $facture->date_echeance;
			$date_ech = date('Ymd', $date_echeance);
			$mod = $facture->mode_reglement_code;
		}
		
		$TData[] = array( // Voir documentation Natixis dans répertoire Eprolor
			'04'															// Type de ligne
			,str_pad($cptLine, 6, 0, STR_PAD_LEFT)							// Compteur
			,'138'															// Ref Natixis
			,$factype														// Facture ou avoir
			,'053506'														// Ref Eprolor chez Natixis // TODO conf global
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
			,str_pad(round(abs($facture->total_ttc)*100), 15, 0, STR_PAD_LEFT)			// Montant (toujours positif d'après la doc)
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

 
function _parseTifViaExcel($db, $TRefFacture)
{
    global $conf, $langs;
    
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    
    $headers = array(
        'Code Affacturage',
        'Num Cpte Client',
        'Num Doc', 
        'Montant Doc',
        'Type Doc',
        'Date Doc',
        'Date Ech',
        'Mode Rgt',
        'Commentaire',
        'Devise'
    );
    
    // Écrire les en-têtes
    $col = 1;
    $row = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, $row, $header);
        $col++;
    }
    
    // Récupérer et formater les données
    $TError = array();
    $row = 2; // Ligne Excel (après les en-têtes)
    
    foreach ($TRefFacture as $ref) {
        $facture = new Facture($db);
        if ($facture->fetch('', $ref) < 0) continue;
        $facture->fetch_thirdparty();
        
        if($facture->thirdparty){
            $facture->thirdparty->fetch_optionals();
            $factor_code = $facture->thirdparty->array_options["options_factor_code"] ?? '';
        }else{
            $factor_code = '';
        }
        
        $date_echeance_timestamp = null;
        
        // --- Vérifications ---
        if (empty($facture->thirdparty->code_compta)) {
            $TError['TErrorCodeCompta'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyCodeCompta', $facture->ref, $facture->thirdparty->name);
        }
        if (empty($facture->mode_reglement_code)) {
            $TError['TErrorModeReglt'][] = $langs->transnoentitiesnoconv('ErrorFactorEmptyModeReglt', $facture->ref);
        }
        
        // --- Traitement du Type de Facture et de la Date d'Échéance ---
        $factype = 'FA'; // Par défaut : Facture
        $excel_date_ech = '';
        
        if ($facture->type == 2) {
            $factype = 'AV'; 
        } else {
            
            if (!empty($facture->date_lim_reglement)) {
                $date_echeance_timestamp = $facture->date_lim_reglement;
            } elseif (!empty($facture->date_echeance)) {
                $date_echeance_timestamp = $facture->date_echeance;
            }
            
            if ($date_echeance_timestamp) {
                
                $date_ech_obj = \DateTime::createFromFormat('U', (string)$date_echeance_timestamp);
                if ($date_ech_obj) {
                    $excel_date_ech = Date::PHPToExcel($date_ech_obj);
                }
            }
        }
        
        
        $excel_date_doc = '';
        if (!empty($facture->date)) {
           
            $date_doc_obj = \DateTime::createFromFormat('U', (string)$facture->date);
            if ($date_doc_obj) {
                $excel_date_doc = Date::PHPToExcel($date_doc_obj);
            }
        }
        

        // Récupérer le mode de règlement
        $mode_reglement = $facture->mode_reglement_code ? $facture->mode_reglement_code : '';
        
        
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $factor_code);                       //A - Code Affacturage
        $sheet->setCellValueByColumnAndRow($col++, $row, $facture->thirdparty->code_compta); // B - Num Cpte Client
        $sheet->setCellValueByColumnAndRow($col++, $row, $facture->ref);                      // C - Num Doc
        $sheet->setCellValueByColumnAndRow($col++, $row, $facture->total_ttc);                // D - Montant Doc (Formaté)
        $sheet->setCellValueByColumnAndRow($col++, $row, $factype);                           // E - Type Doc
        $sheet->setCellValueByColumnAndRow($col++, $row, $excel_date_doc);                    // F - Date Doc (Format Excel)
        $sheet->setCellValueByColumnAndRow($col++, $row, $excel_date_ech);                    // G - Date Ech (Format Excel)
        $sheet->setCellValueByColumnAndRow($col++, $row, $mode_reglement);                    // H - Mode Rgt
        $sheet->setCellValueByColumnAndRow($col++, $row, '');                                 // I - Commentaire (vide)
        $sheet->setCellValueByColumnAndRow($col++, $row, $conf->currency);                    // J - Devise
        
        $row++;
    }
    
    if (!empty($TError['TErrorCodeCompta'])) {
        $_SESSION['TErrorCodeCompta'] = $TError['TErrorCodeCompta'];
    } else {
        unset($_SESSION['TErrorCodeCompta']);
    }
    if (!empty($TError['TErrorModeReglt'])) {
        $_SESSION['TErrorModeReglt'] = $TError['TErrorModeReglt'];
    } else {
        unset($_SESSION['TErrorModeReglt']);
    }

    // Formatage En-têtes
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    
    // Format des nombres pour la colonne Montant (D)
    $sheet->getStyle('D2:D'.($row-1))
          ->getNumberFormat()
          ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

    // Format des dates pour les colonnes Date Doc (F) et Date Ech (G)
    $date_format = 'dd/mm/yyyy'; 

    $sheet->getStyle('E2:G'.($row-1))
          ->getNumberFormat()
          ->setFormatCode($date_format);
    
    // Auto-adjuster la largeur des colonnes
    foreach (range('A','J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // --- Sauvegarde du fichier ---
    
    $folder = ($conf->entity == 1) ? DOL_DATA_ROOT.'/factor/' : DOL_DATA_ROOT.'/'.$conf->entity.'/factor/';
    dol_mkdir($folder);
    
    // Format de sortie demandé
    $output_format = 'Xls'; // .xls (Excel 97-2003)
    
    // Nom du fichier incluant l'heure 
    $fileName = 'export_natixis_'.date('Ymd_His').'.'.strtolower($output_format);
    $fullPath = $folder.$fileName;
    
    // Utilisation de IOFactory::createWriter
    try {
        $writer = IOFactory::createWriter($spreadsheet, $output_format);
        $writer->save($fullPath);
        
        if (file_exists($fullPath)) {
            return $fileName;
        } else {
            // Mettre à jour la session d'erreur si le fichier n'est pas trouvé
            $_SESSION['TErrorFile'][] = $langs->transnoentitiesnoconv('ErrorFileNotCreated')." (Path: ".$fullPath.")";
            return false;
        }
    } catch (\Exception $e) { // Utilisation du namespace \Exception pour attraper toute exception
        $_SESSION['TErrorFile'][] = $langs->transnoentitiesnoconv('ErrorFileCreationFailed').': '.$e->getMessage();
        return false;
    }
}