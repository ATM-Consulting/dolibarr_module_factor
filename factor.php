<?php
/* Copyright (C) 2002-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *		\file       htdocs/compta/facture/impayees.php
 *		\ingroup    facture
 *		\brief      Page to list and build liste of unpaid invoices
 */

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

dol_include_once('/factor/class/factor.class.php');
dol_include_once('/factor/lib/factor.lib.php');

$langs->load("bills");
$langs->load("factor@factor");

$id = (GETPOST('facid','int') ? GETPOST('facid','int') : GETPOST('id','int'));
$action = GETPOST('action','alpha');
$export_txt = GETPOST('export_txt', 'alpha');
if (!empty($export_txt)) $action = 'export';

$option = GETPOST('option', 'none');
$builddoc_generatebutton=GETPOST('builddoc_generatebutton', 'none');
$factor_depot_classify = GETPOST('factor_depot_classify', 'none');
// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user,'facture',$id,'');

$diroutputpdf=$conf->facture->dir_output . '/unpaid/temp';
if (! $user->rights->societe->client->voir || $socid) $diroutputpdf.='/private/'.$user->id;	// If user has no permission to see all, output dir is specific to user


if(!empty($factor_depot_classify)) {

	$TFactorDepot = GETPOST('toGenerate', 'none');
	//var_dump($TFactorDepot);
	if(!empty($TFactorDepot)) {
		foreach($TFactorDepot as $facref) {
			$f=new Facture($db);
			$f->fetch(0,$facref);

			$f->array_options['options_factor_depot'] = 1;
			$f->insertExtraFields();

			TFactor::addEvent($facid,$f->ref);

		}
	}

	setEventMessage('BillsClassifyDeposed');

}


/*
 * Action
 */

if ($action == "builddoc" && $user->rights->facture->lire && ! GETPOST('button_search', 'none') && !empty($builddoc_generatebutton))
{
	if (is_array($_POST['toGenerate']))
	{
	    $arrayofinclusion=array();
	    foreach($_POST['toGenerate'] as $tmppdf) $arrayofinclusion[]=preg_quote($tmppdf.'.pdf','/');
		$factures = dol_dir_list($conf->facture->dir_output,'all',1,implode('|',$arrayofinclusion),'\.meta$|\.png','date',SORT_DESC);

		// liste les fichiers
		$files = array();
		$factures_bak = $factures ;
		foreach($_POST['toGenerate'] as $basename){
			foreach($factures as $facture){
				if(strstr($facture["name"],$basename)){
					$files[] = $conf->facture->dir_output.'/'.$basename.'/'.$facture["name"];
				}
			}
		}

        // Define output language (Here it is not used because we do only merging existing PDF)
        $outputlangs = $langs;
        $newlang='';
        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'none')) $newlang=GETPOST('lang_id', 'none');
        if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
        if (! empty($newlang))
        {
            $outputlangs = new Translate("",$conf);
            $outputlangs->setDefaultLang($newlang);
        }

        // Create empty PDF
        $pdf=pdf_getInstance();
        if (class_exists('TCPDF'))
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($outputlangs));

        if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

		// Add all others
		foreach($files as $file)
		{
			// Charge un document PDF depuis un fichier.
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++)
			{
				$tplidx = $pdf->importPage($i);
				$s = $pdf->getTemplatesize($tplidx);
				$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
		}

		// Create output dir if not exists
		dol_mkdir($diroutputpdf);

		// Save merged file
		$filename=strtolower(dol_sanitizeFileName($langs->transnoentities("Factor")));
		if ($option=='late') $filename.='_'.strtolower(dol_sanitizeFileName($langs->transnoentities("Late")));
		if ($pagecount)
		{
			$now=dol_now();
			$file=$diroutputpdf.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.pdf';
			$pdf->Output($file,'F');
			if (! empty($conf->global->MAIN_UMASK))
			@chmod($file, octdec($conf->global->MAIN_UMASK));
		}
		else
		{
			$mesg='<div class="error">'.$langs->trans('NoPDFAvailableForChecked').'</div>';
		}
	}
	else
	{
		$mesg='<div class="error">'.$langs->trans('InvoiceNotChecked').'</div>' ;
	}
}

// Remove file
if ($action == 'remove_file')
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$langs->load("other");
	$upload_dir = $diroutputpdf;
	$file = $upload_dir . '/' . GETPOST('file', 'none');
	$ret=dol_delete_file($file,0,0,0,'');
	if ($ret) setEventMessage($langs->trans("FileWasRemoved", GETPOST('urlfile', 'none')));
	else setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('urlfile', 'none')), 'errors');
	$action='';
}

if ($action == 'export')
{
	$format = GETPOST('format', 'alpha');
	$TRefFacture = GETPOST('toGenerate', 'array');

	_export_factures($db, $format, $TRefFacture);
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title=$langs->trans("FactorBills");

llxHeader('',$title);

$TErrorCodeCompta = $_SESSION['TErrorCodeCompta'];
$TErrorModeReglt = $_SESSION['TErrorModeReglt'];
unset($_SESSION['TErrorCodeCompta']);
unset($_SESSION['TErrorModeReglt']);

if ($TErrorCodeCompta)
{
	print '<div class="warning">';
	foreach ($TErrorCodeCompta as $error)
	{
		print '<p>'.$error.'</p>';
	}
	print '</div>';
}

if ($TErrorModeReglt)
{
	print '<div class="warning">';
	foreach ($TErrorModeReglt as $error)
	{
		print '<p>'.$error.'</p>';
	}
	print '</div>';
}

?>
<script type="text/javascript">
$(document).ready(function() {
	$("#checkall").click(function() {
		$(".checkformerge").attr('checked', true);
	});
	$("#checknone").click(function() {
		$(".checkformerge").attr('checked', false);
	});
});
</script>
<?php

$now=dol_now();

$search_ref = GETPOST("search_ref", 'alpha');
$search_refcustomer=GETPOST('search_refcustomer', 'alpha');
$search_societe = GETPOST("search_societe", 'alpha');
$search_montant_ht = GETPOST("search_montant_ht", 'alpha');
$search_montant_ttc = GETPOST("search_montant_ttc", 'alpha');
$late = GETPOST("late", 'none');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield="f.date_lim_reglement";
if (! $sortorder) $sortorder="ASC";

$limit = $conf->liste_limit;

$factor_depot = GETPOST('factor_depot','int');

$db->query('SET SESSION sql_mode = \'\';');

$invoiceRefDBField = floatval(DOL_VERSION) >= 10 ? 'ref' : 'facnumber';

$sql = "SELECT s.nom, s.rowid as socid";
$sql.= ", f.rowid as facid, f." . $invoiceRefDBField . " as facnumber, f.ref_client,f.date_valid as datev, f.increment, f.total as total_ht, f.tva as total_tva, f.total_ttc, f.localtax1, f.localtax2, f.revenuestamp";
$sql.= ", f.datef as df, f.date_lim_reglement as datelimite";
$sql.= ", f.paye as paye, f.fk_statut, f.type,fex.factor_depot";
$sql.= ", sum(pf.amount) as am";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", sc.fk_soc, sc.fk_user ";
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields sex ON (sex.fk_object = s.rowid)";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON (s.rowid = sc.fk_soc) ";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture as f ON (f.fk_soc = s.rowid)
	 LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fex ON (fex.fk_object = f.rowid)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON (f.rowid=pf.fk_facture) ";
$sql.= " WHERE sex.factor_suivi=1";
$sql.= " AND f.entity = ".$conf->entity;
$sql.= " AND f.type IN (0,1,2,3) AND f.fk_statut = 1";

if(empty($factor_depot)) $sql.=" AND (fex.factor_depot!=1 OR fex.factor_depot IS NULL) ";
else $sql.=" AND fex.factor_depot=1";

if ($option == 'late') $sql.=" AND f.date_lim_reglement < '".$db->idate(dol_now() - $conf->facture->client->warning_delay)."'";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= " AND sc.fk_user = " .$user->id;
if (! empty($socid)) $sql .= " AND s.rowid = ".$socid;
if ($search_ref)         $sql .= " AND f." . $invoiceRefDBField . " LIKE '%".$db->escape($search_ref)."%'";
if ($search_refcustomer) $sql .= " AND f.ref_client LIKE '%".$db->escape($search_refcustomer)."%'";
if ($search_societe)     $sql .= " AND s.nom LIKE '%".$db->escape($search_societe)."%'";
if ($search_montant_ht)  $sql .= " AND f.total = '".$db->escape($search_montant_ht)."'";
if ($search_montant_ttc) $sql .= " AND f.total_ttc = '".$db->escape($search_montant_ttc)."'";
if (GETPOST('sf_ref', 'none'))   $sql .= " AND f." . $invoiceRefDBField . " LIKE '%".$db->escape(GETPOST('sf_ref', 'no'))."%'";
$sql.= " GROUP BY s.nom, s.rowid, f.rowid, f." . $invoiceRefDBField . " ";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", sc.fk_soc, sc.fk_user ";
$sql.= " ORDER BY ";
$listfield=explode(',',$sortfield);
foreach ($listfield as $key => $value) $sql.=$listfield[$key]." ".$sortorder.",";
$sql.= " f." . $invoiceRefDBField . " DESC";

//$sql .= $db->plimit($limit+1,$offset);
//print $sql;
$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);

	if (! empty($socid))
	{
		$soc = new Societe($db);
		$soc->fetch($socid);
	}

	$param="";
	$param.=(! empty($socid)?"&amp;socid=".$socid:"");
	$param.=(! empty($option)?"&amp;option=".$option:"");
	if ($search_ref)         $param.='&amp;search_ref='.urlencode($search_ref);
    	if ($search_refcustomer) $param.='&amp;search_ref='.urlencode($search_refcustomer);
	if ($search_societe)     $param.='&amp;search_societe='.urlencode($search_societe);
	if ($search_montant_ht)  $param.='&amp;search_montant_ht='.urlencode($search_montant_ht);
	if ($search_montant_ttc) $param.='&amp;search_montant_ttc='.urlencode($search_montant_ttc);
	if ($late)               $param.='&amp;late='.urlencode($late);

	$urlsource=$_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource.=str_replace('&amp;','&',$param);

	$titre=$langs->trans("FactorBills");

	print_fiche_titre($titre);
	//print_barre_liste($titre,$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',0);	// We don't want pagination on this page

	dol_htmloutput_mesg($mesg);

	print '<form id="form_generate_pdf" method="POST" action="'.$_SERVER["PHP_SELF"].'?sortfield='. $sortfield .'&sortorder='. $sortorder .'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	if ($late) print '<input type="hidden" name="late" value="'.dol_escape_htmltag($late).'">';

	$i = 0;
	print '<table class="liste" width="100%">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"f." . $invoiceRefDBField,"",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('RefCustomer'),$_SERVER["PHP_SELF"],'f.ref_client','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"],"f.datef","",$param,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("DateDue"),$_SERVER["PHP_SELF"],"f.date_lim_reglement","",$param,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Company"),$_SERVER["PHP_SELF"],"s.nom","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("AmountHT"),$_SERVER["PHP_SELF"],"f.total","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Taxes"),$_SERVER["PHP_SELF"],"f.tva","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("AmountTTC"),$_SERVER["PHP_SELF"],"f.total_ttc","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Received"),$_SERVER["PHP_SELF"],"am","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Rest"),$_SERVER["PHP_SELF"],"am","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"],"fk_statut,paye,am","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("MergeOrDeposit"),$_SERVER["PHP_SELF"],"","",$param,'align="center"',$sortfield,$sortorder);
	print "</tr>\n";

	// Lignes des champs de filtre
	print '<tr class="liste_titre">';
	// Ref
	print '<td class="liste_titre">';
	print '<input class="flat" size="10" type="text" name="search_ref" value="'.$search_ref.'"></td>';
    print '<td class="liste_titre">';
    print '<input class="flat" size="6" type="text" name="search_refcustomer" value="'.$search_refcustomer.'">';
    print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="left"><input class="flat" type="text" size="10" name="search_societe" value="'.dol_escape_htmltag($search_societe).'"></td>';
	print '<td class="liste_titre" align="right"><input class="flat" type="text" size="8" name="search_montant_ht" value="'.dol_escape_htmltag($search_montant_ht).'"></td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right"><input class="flat" type="text" size="8" name="search_montant_ttc" value="'.dol_escape_htmltag($search_montant_ttc).'"></td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td>';
	print '<td class="liste_titre" align="center">';
	if ($conf->use_javascript_ajax) print '<a href="#" id="checkall">'.$langs->trans("All").'</a> / <a href="#" id="checknone">'.$langs->trans("None").'</a>';
	print '</td>';
	print "</tr>\n";

	if ($num > 0)
	{
		$var=True;
		$total_ht=0;
		$total_tva=0;
		$total_ttc=0;
		$total_paid=0;

		$facturestatic=new Facture($db);

		while ($i < $num)
		{
			$objp = $db->fetch_object($resql);
			$date_limit=$db->jdate($objp->datelimite);

			$var=!$var;

			print "<tr ".$bc[$var].">";
			$classname = "impayee";

			print '<td class="nowrap">';

			$facturestatic->id=$objp->facid;
			$facturestatic->ref=$objp->facnumber;
			$facturestatic->type=$objp->type;

			print '<table class="nobordernopadding"><tr class="nocellnopadd">';

			// Ref
			print '<td class="nobordernopadding nowrap">';
			print $facturestatic->getNomUrl(1);
			print '</td>';

			// Warning picto
			print '<td width="20" class="nobordernopadding nowrap">';
			if ($date_limit < ($now - $conf->facture->client->warning_delay) && ! $objp->paye && $objp->fk_statut == 1) print img_warning($langs->trans("Late"));
			print '</td>';

			// PDF Picto
			print '<td width="16" align="right" class="nobordernopadding hideonsmartphone">';
            $filename=dol_sanitizeFileName($objp->facnumber);
			$filedir=$conf->facture->dir_output . '/' . dol_sanitizeFileName($objp->facnumber);
			print $formfile->getDocumentsLink($facturestatic->element, $filename, $filedir);
			if($now - strtotime($objp->datelimite) > $conf->global->FACTOR_LIMIT_DEPOT * 86400 && $objp->factor_depot != 1) {
				print img_warning($langs->trans("LateDepot"));
			}
            print '</td>';

			print '</tr></table>';

			print "</td>\n";

            // Customer ref
            print '<td class="nowrap">';
            print $objp->ref_client;
            print '</td>';

			print '<td class="nowrap" align="center">'.dol_print_date($db->jdate($objp->df),'day').'</td>'."\n";
			print '<td class="nowrap" align="center">'.dol_print_date($db->jdate($objp->datelimite),'day').'</td>'."\n";

			if ((float) DOL_VERSION >= 3.7) $card_url = DOL_URL_ROOT.'/comm/card.php';
			else $card_url = DOL_URL_ROOT.'/comm/fiche.php';

			print '<td><a href="'.$card_url.'?socid='.$objp->socid.'">'.img_object($langs->trans("ShowCompany"),"company").' '.dol_trunc($objp->nom,28).'</a></td>';

			print '<td align="right">'.price($objp->total_ht).'</td>';
			print '<td align="right">'.price($objp->total_tva);
			$tx1=price2num($objp->localtax1);
			$tx2=price2num($objp->localtax2);
			$revenuestamp=price2num($objp->revenuestamp);
			if (! empty($tx1) || ! empty($tx2) || ! empty($revenuestamp)) print '+'.price($tx1 + $tx2 + $revenuestamp);
			print '</td>';
			print '<td align="right">'.price($objp->total_ttc).'</td>';
			print '<td align="right">';
			$cn=$facturestatic->getSumCreditNotesUsed();
			if (! empty($objp->am)) print price($objp->am);
			if (! empty($objp->am) && ! empty($cn)) print '+';
			if (! empty($cn)) print price($cn);
			print '</td>';

			// Remain to receive
			print '<td align="right">'.((! empty($objp->am) || ! empty($cn))?price($objp->total_ttc-$objp->am-$cn):'&nbsp;').'</td>';

			// Status of invoice
			print '<td align="right" class="nowrap">';
			print $facturestatic->LibStatut($objp->paye,$objp->fk_statut,5,$objp->am);
			print '</td>';

			// Checkbox
			print '<td align="center">';
			if (! empty($formfile->infofiles['nboffiles']))
				print '<input id="cb'.$objp->facid.'" class="flat checkformerge" type="checkbox" name="toGenerate[]" value="'.$objp->facnumber.'">';
			else
				print '&nbsp;';
			print '</td>' ;

			print "</tr>\n";
			$total_ht+=$objp->total_ht;
			$total_tva+=($objp->total_tva + $tx1 + $tx2 + $revenuestamp);
			$total_ttc+=$objp->total_ttc;
			$total_paid+=$objp->am + $cn;

			$i++;
		}

		print '<tr class="liste_total">';
		print '<td colspan="5" align="left">'.$langs->trans("Total").'</td>';
		print '<td align="right"><b>'.price($total_ht).'</b></td>';
		print '<td align="right"><b>'.price($total_tva).'</b></td>';
		print '<td align="right"><b>'.price($total_ttc).'</b></td>';
		print '<td align="right"><b>'.price($total_paid).'</b></td>';
		print '<td align="right"><b>'.price($total_ttc - $total_paid).'</b></td>';
		print '<td align="center">&nbsp;</td>';
		print '<td align="center">&nbsp;</td>';
		print "</tr>\n";
	}

	print "</table>";

	if(empty($factor_depot)) {
		print "<div class='tabsAction'>";
		print "<select name='format'><option value='natixis'>Natixis</option></select>&nbsp;";
		print "<input class='button' type='submit' name='export_txt' value='".$langs->transnoentitiesnoconv('FileExport')."' />";
		print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		print "<input class='button' type='submit' name='factor_depot_classify' value='".$langs->trans('ClassifyDepot')."' />";
		print "</div>";
	}

	/*
	 * Show list of available documents
	 */
	$filedir=$diroutputpdf;
	$genallowed=$user->rights->facture->lire;
	$delallowed=$user->rights->facture->lire;

	print '<br>';
	print '<input type="hidden" name="option" value="'.$option.'">';
	// We disable multilang because we concat already existing pdf.
	$formfile->show_documents('facture','unpaid/temp',$filedir,$urlsource,$genallowed,$delallowed,'',1,1,0,48,1,$param,$langs->trans("PDFMerge"),$langs->trans("PDFMerge"));
	print '</form>';

	$db->free($resql);
}
else dol_print_error($db,'');


llxFooter();
$db->close();

?>
