<?php
/* Copyright (C) 2011-2019	Alexandre Spangaro	<aspangaro@open-dsi.fr>
 * Copyright (C) 2015-2016	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2015		Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2021		Gauthier VERDOL         <gauthier.verdol@atm-consulting.fr>
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

/**
 *	    \file       htdocs/salaries/list.php
 *      \ingroup    salaries
 *		\brief     	List of salaries payments
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/salaries/class/paymentsalary.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
if (!empty($conf->accounting->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array("compta", "salaries", "bills", "hrm"));

$action     = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'bomlist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) {
	$page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = "s.datep,s.rowid";
}
if (!$sortorder) {
	$sortorder = "DESC,DESC";
}

// Initialize technical objects
$object = new PaymentSalary($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->user->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('salarieslist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

if (!$sortfield) {
	$sortfield = "s.datep,s.rowid";
}
if (!$sortorder) {
	$sortorder = "DESC,DESC";
}

$search_ref = GETPOST('search_ref', 'int');
$search_user = GETPOST('search_user', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_date_start_from = dol_mktime(0, 0, 0, GETPOST('search_date_start_frommonth', 'int'), GETPOST('search_date_start_fromday', 'int'), GETPOST('search_date_start_fromyear', 'int'));
$search_date_start_to = dol_mktime(23, 59, 59, GETPOST('search_date_start_tomonth', 'int'), GETPOST('search_date_start_today', 'int'), GETPOST('search_date_start_toyear', 'int'));
$search_date_end_from = dol_mktime(0, 0, 0, GETPOST('search_date_end_frommonth', 'int'), GETPOST('search_date_end_fromday', 'int'), GETPOST('search_date_end_fromyear', 'int'));
$search_date_end_to = dol_mktime(23, 59, 59, GETPOST('search_date_end_tomonth', 'int'), GETPOST('search_date_end_today', 'int'), GETPOST('search_date_end_toyear', 'int'));
$search_amount = GETPOST('search_amount', 'alpha');
$search_account = GETPOST('search_account', 'int');
$search_status = GETPOST('search_status', 'int');

$filtre = GETPOST("filtre", 'restricthtml');

if (!GETPOST('search_type_id', 'int')) {
	$newfiltre = str_replace('filtre=', '', $filtre);
	$filterarray = explode('-', $newfiltre);
	foreach ($filterarray as $val) {
		$part = explode(':', $val);
		if ($part[0] == 's.fk_typepayment') {
			$search_type_id = $part[1];
		}
	}
} else {
	$search_type_id = GETPOST('search_type_id', 'int');
}

$childids = $user->getAllChildIds(1);

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOST('search_'.$key.'_dtstartmonth', 'int'), GETPOST('search_'.$key.'_dtstartday', 'int'), GETPOST('search_'.$key.'_dtstartyear', 'int'));
		$search[$key.'_dtend'] = dol_mktime(23, 59, 59, GETPOST('search_'.$key.'_dtendmonth', 'int'), GETPOST('search_'.$key.'_dtendday', 'int'), GETPOST('search_'.$key.'_dtendyear', 'int'));
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['searchall'])) {
		$fieldstosearchall['t.'.$key] = $val['label'];
	}
}

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval($val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label'=>$val['label'],
			'checked'=>(($visible < 0) ? 0 : 1),
			'enabled'=>($visible != 3 && dol_eval($val['enabled'], 1)),
			'position'=>$val['position'],
			'help'=>$val['help']
		);
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

$permissiontoread = $user->rights->salaries->read;
$permissiontoadd = $user->rights->salaries->write;
$permissiontodelete = $user->rights->salaries->delete;

// Security check
$socid = GETPOST("socid", "int");
if ($user->socid) {
	$socid = $user->socid;
}
restrictedArea($user, 'salaries', '', 'salary', '');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All test are required to be compatible with all browsers
		$search_ref = "";
		$search_user = "";
		$search_label = "";
		$search_date_start_from = '';
		$search_date_start_to = '';
		$search_date_end_from = '';
		$search_date_end_to = '';
		$search_date_end = '';
		$search_amount = "";
		$search_account = '';
		$search_status = '';
		$search_type_id = "";
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'PaymentSalary';
	$objectlabel = 'SalariesPayments';
	$uploaddir = $conf->salaries->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

	// Validate records
	if (!$error && $massaction == 'buildsepa' && $permissiontoadd) {
		$objecttmp = new $objectclass($db);

		// TODO
	}
}

/*
 * View
 */

$form = new Form($db);
$salstatic = new Salary($db);
$userstatic = new User($db);
$accountstatic = new Account($db);

$now = dol_now();

//$help_url="EN:Module_BillOfMaterials|FR:Module_BillOfMaterials_FR|ES:Módulo_BillOfMaterials";
$help_url = '';
$title = $langs->trans('Salaries');

$sql = "SELECT u.rowid as uid, u.lastname, u.firstname, u.login, u.email, u.admin, u.salary as current_salary, u.fk_soc as fk_soc, u.statut as status,";
$sql .= " s.rowid, s.fk_account, s.paye, s.fk_user, s.amount, s.salary, s.label, s.datesp, s.dateep, s.fk_typepayment as paymenttype, ";
$sql .= " ba.rowid as bid, ba.ref as bref, ba.number as bnumber, ba.account_number, ba.fk_accountancy_journal, ba.label as blabel, ba.iban_prefix as iban, ba.bic, ba.currency_code, ba.clos,";
$sql .= " pst.code as payment_code,";
$sql .= " SUM(ps.amount) as alreadypayed";
$sql .= " FROM ".MAIN_DB_PREFIX."salary as s";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."payment_salary as ps ON (ps.fk_salary = s.rowid) ";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as pst ON (s.fk_typepayment = pst.id) ";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account ba ON (ba.rowid = s.fk_account), ";
//$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."payment_salary as ps ON ps.fk_salary = s.rowid, ";
$sql .= " ".MAIN_DB_PREFIX."user as u";
$sql .= " WHERE u.rowid = s.fk_user";
$sql .= " AND s.entity IN (".getEntity('payment_salaries').")";
if (empty($user->rights->salaries->readall)) {
	$sql .= " AND s.fk_user IN (".$db->sanitize(join(',', $childids)).")";
}

// Search criteria
if ($search_ref) {
	$sql .= " AND s.rowid=".((int) $search_ref);
}
if ($search_user) {
	$sql .= natural_search(array('u.login', 'u.lastname', 'u.firstname', 'u.email'), $search_user);
}
if ($search_label) {
	$sql .= natural_search(array('s.label'), $search_label);
}
if (!empty($search_date_start_from) && !empty($search_date_start_to)) {
	$sql .= " AND s.datesp BETWEEN '".$db->idate($search_date_start_from)."' AND '".$db->idate($search_date_start_to)."'";
}
if (!empty($search_date_end_from) && !empty($search_date_end_to)) {
	$sql .= " AND s.dateep BETWEEN '".$db->idate($search_date_end_from)."' AND '".$db->idate($search_date_end_to)."'";
}
if ($search_amount) {
	$sql .= natural_search("s.amount", $search_amount, 1);
}
if ($search_account > 0) {
	$sql .= " AND s.fk_account=".((int) $search_account);
}
if ($search_status != '' && $search_status >= 0) {
	$sql .= " AND s.paye = ".((int) $search_status);
}
if ($search_type_id) {
	$sql .= " AND s.fk_typepayment=".((int) $search_type_id);
}
$sql .= " GROUP BY u.rowid, u.lastname, u.firstname, u.login, u.email, u.admin, u.salary, u.fk_soc, u.statut,";
$sql .= " s.rowid, s.fk_account, s.paye, s.fk_user, s.amount, s.salary, s.label, s.datesp, s.dateep, s.fk_typepayment, s.fk_bank,";
$sql .= " ba.rowid, ba.ref, ba.number, ba.account_number, ba.fk_accountancy_journal, ba.label, ba.iban_prefix, ba.bic, ba.currency_code, ba.clos,";
$sql .= " pst.code";
$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total of record found is smaller than page * limit, goto and load page 0
		$page = 0;
		$offset = 0;
	}
}
// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
	$num = $nbtotalofrecords;
} else {
	if ($limit) {
		$sql .= $db->plimit($limit + 1, $offset);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}

// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url);

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.urlencode($limit);
}
if ($search_type_id) {
	$param .= '&search_type_id='.urlencode($search_type_id);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
if ($search_ref) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if ($search_user > 0) {
	$param .= '&search_user='.urlencode($search_user);
}
if ($search_label) {
	$param .= '&search_label='.urlencode($search_label);
}
if ($search_account) {
	$param .= '&search_account='.urlencode($search_account);
}
if ($search_status != '' && $search_status != '-1') {
	$param .= '&search_status='.urlencode($search_status);
}
if (!empty($search_date_start_from)) {
	$param .= '&search_date_start_fromday='.urlencode(GETPOST('search_date_start_fromday')).'&search_date_start_frommonth='.urlencode(GETPOST('search_date_start_frommonth')).'&search_date_start_fromyear='.urlencode(GETPOST('search_date_start_fromyear'));
}
if (!empty($search_date_start_to)) {
	$param .= "&search_date_start_today=".urlencode(GETPOST('search_date_start_today'))."&search_date_start_tomonth=".urlencode(GETPOST('search_date_start_tomonth'))."&search_date_start_toyear=".urlencode(GETPOST('search_date_start_toyear'));
}
if (!empty($search_date_end_from)) {
	$param .= '&search_date_end_fromday='.urlencode(GETPOST('search_date_end_fromday')).'&search_date_end_frommonth='.urlencode(GETPOST('search_date_end_frommonth')).'&search_date_end_fromyear='.urlencode(GETPOST('search_date_end_fromyear'));
}
if (!empty($search_date_end_to)) {
	$param .= "&search_date_end_today=".urlencode(GETPOST('search_date_end_today'))."&search_date_end_tomonth=".urlencode(GETPOST('search_date_end_tomonth'))."&search_date_end_toyear=".urlencode(GETPOST('search_date_end_toyear'));
}

// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions = array(
	//'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
	//'buildsepa'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("BuildSepa"),	// TODO
);
//if ($permissiontodelete) $arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$url = DOL_URL_ROOT.'/salaries/card.php?action=create';
if (!empty($socid)) {
	$url .= '&socid='.$socid;
}
$newcardbutton = dolGetButtonTitle($langs->trans('NewSalaryPayment'), '', 'fa fa-plus-circle', $url, '', $user->rights->salaries->write);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'salary', 0, $newcardbutton, '', $limit, 0, 0, 1);

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
//$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
$selectedfields = '';
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

$moreforfilter = '';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';
// Ref
print '<td class="liste_titre left">';
print '<input class="flat" type="text" size="3" name="search_ref" value="'.$db->escape($search_ref).'">';
print '</td>';
// Label
print '<td class="liste_titre"><input type="text" class="flat width150" name="search_label" value="'.$db->escape($search_label).'"></td>';

// Date start
print '<td class="liste_titre center">';
print '<div class="nowrap">';
print $form->selectDate($search_date_start_from ? $search_date_start_from : -1, 'search_date_start_from', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print '</div>';
print '<div class="nowrap">';
print $form->selectDate($search_date_start_to ? $search_date_start_to : -1, 'search_date_start_to', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
print '</div>';
print '</td>';

// Date End
print '<td class="liste_titre center">';
print '<div class="nowrap">';
print $form->selectDate($search_date_end_from ? $search_date_end_from : -1, 'search_date_end_from', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print '</div>';
print '<div class="nowrap">';
print $form->selectDate($search_date_end_to ? $search_date_end_to : -1, 'search_date_end_to', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
print '</div>';
print '</td>';

// Employee
print '<td class="liste_titre">';
print '<input class="flat" type="text" size="6" name="search_user" value="'.$db->escape($search_user).'">';
print '</td>';

// Type
print '<td class="liste_titre left">';
$form->select_types_paiements($search_type_id, 'search_type_id', '', 0, 1, 1, 16);
print '</td>';

// Bank account
if (!empty($conf->banque->enabled)) {
	print '<td class="liste_titre">';
	$form->select_comptes($search_account, 'search_account', 0, '', 1);
	print '</td>';
}

// Amount
print '<td class="liste_titre right"><input name="search_amount" class="flat" type="text" size="8" value="'.$db->escape($search_amount).'"></td>';

print '<td class="liste_titre maxwidthonsmartphone right">';
$liststatus = array('0' => $langs->trans("Unpaid"), '1' => $langs->trans("Paid"));
print $form->selectarray('search_status', $liststatus, $search_status, 1);
print '</td>';

// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";


// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "s.rowid", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre("Label", $_SERVER["PHP_SELF"], "s.label", "", $param, 'class="left"', $sortfield, $sortorder);
print_liste_field_titre("DateStart", $_SERVER["PHP_SELF"], "s.datesp,s.rowid", "", $param, 'align="center"', $sortfield, $sortorder);
print_liste_field_titre("DateEnd", $_SERVER["PHP_SELF"], "s.dateep,s.rowid", "", $param, 'align="center"', $sortfield, $sortorder);
print_liste_field_titre("Employee", $_SERVER["PHP_SELF"], "u.lastname", "", $param, "", $sortfield, $sortorder);
print_liste_field_titre("DefaultPaymentMode", $_SERVER["PHP_SELF"], "type", "", $param, 'class="left"', $sortfield, $sortorder);
if (!empty($conf->banque->enabled)) {
	print_liste_field_titre("DefaultBankAccount", $_SERVER["PHP_SELF"], "ba.label", "", $param, "", $sortfield, $sortorder);
}
print_liste_field_titre("Amount", $_SERVER["PHP_SELF"], "s.amount", "", $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER["PHP_SELF"], "s.paye", '', $param, 'class="right"', $sortfield, $sortorder);
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>'."\n";


// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val) {
		if (preg_match('/\$object/', $val)) {
			$needToFetchEachLine++; // There is at least one compute field that use $object
		}
	}
}

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$total = 0;
$totalarray = array();
while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	$userstatic->id = $obj->uid;
	$userstatic->lastname = $obj->lastname;
	$userstatic->firstname = $obj->firstname;
	$userstatic->admin = $obj->admin;
	$userstatic->login = $obj->login;
	$userstatic->email = $obj->email;
	$userstatic->socid = $obj->fk_soc;
	$userstatic->statut = $obj->status;

	$salstatic->id = $obj->rowid;
	$salstatic->ref = $obj->rowid;
	$salstatic->label = $obj->label;
	$salstatic->paye = $obj->paye;
	$salstatic->datesp = $db->jdate($obj->datesp);
	$salstatic->dateep = $db->jdate($obj->dateep);

	// Show here line of result
	print '<tr class="oddeven">';

	// Ref
	print "<td>".$salstatic->getNomUrl(1)."</td>\n";
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Label payment
	print "<td>".dol_trunc($obj->label, 40)."</td>\n";
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Date Start
	print '<td class="center">'.dol_print_date($db->jdate($obj->datesp), 'day')."</td>\n";
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Date End
	print '<td class="center">'.dol_print_date($db->jdate($obj->dateep), 'day')."</td>\n";
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Employee
	print "<td>".$userstatic->getNomUrl(1)."</td>\n";
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Type
	print '<td>';
	if (!empty($obj->payment_code)) print $langs->trans("PaymentTypeShort".$obj->payment_code);
	print '</td>';
	if (!$i) {
		$totalarray['nbfield']++;
	}

	// Account
	if (!empty($conf->banque->enabled)) {
		print '<td>';
		if ($obj->fk_account > 0) {
			//$accountstatic->fetch($obj->fk_bank);
			$accountstatic->id = $obj->bid;
			$accountstatic->ref = $obj->bref;
			$accountstatic->label = $obj->blabel;
			$accountstatic->number = $obj->bnumber;
			$accountstatic->iban = $obj->iban;
			$accountstatic->bic = $obj->bic;
			$accountstatic->currency_code = $langs->trans("Currency".$obj->currency_code);
			$accountstatic->account_number = $obj->account_number;
			$accountstatic->clos = $obj->clos;

			if (!empty($conf->accounting->enabled)) {
				$accountstatic->account_number = $obj->account_number;

				$accountingjournal = new AccountingJournal($db);
				$accountingjournal->fetch($obj->fk_accountancy_journal);

				$accountstatic->accountancy_journal = $accountingjournal->getNomUrl(0, 1, 1, '', 1);
			}
			//$accountstatic->label = $obj->blabel;
			print $accountstatic->getNomUrl(1);
		} else {
			print '&nbsp;';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	//  if (!$i) $totalarray['pos'][$totalarray['nbfield']] = 'totalttcfield';

	// Amount
	print '<td class="nowrap right"><span class="amount">'.price($obj->amount).'</span></td>';
	if (!$i) {
		$totalarray['nbfield']++;
	}
	if (!$i) {
		$totalarray['pos'][$totalarray['nbfield']] = 'totalttcfield';
	}
	$totalarray['val']['totalttcfield'] += $obj->amount;

	print '<td class="nowrap right">'.$salstatic->LibStatut($obj->paye, 5, $obj->alreadypayed).'</td>';
	if (!$i) $totalarray['nbfield']++;

	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields'=>$arrayfields, 'object'=>$object, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	// Action column
	print '<td class="nowrap center">';
	if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		$selected = 0;
		if (in_array($object->id, $arrayofselected)) {
			$selected = 1;
		}
		print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
	}
	print '</td>';
	if (!$i) {
		$totalarray['nbfield']++;
	}

	print '</tr>'."\n";

	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';


// If no record found
if ($num == 0) {
	/*$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}*/
	$colspan = 9;
	if (!empty($conf->banque->enabled)) { $colspan++; }
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}


$db->free($resql);

$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";


// End of page
llxFooter();
$db->close();
