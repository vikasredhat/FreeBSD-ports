<?php
/*
 * acme_htmllist.php
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/*
  This file contains functions which are NOT specific to the package and may/could/should 
  be moved to the general pfSense php library for possible easy use by other parts of pfSense
*/

namespace pfsense_pkg\acme;

require_once("config.inc");
require_once("acme_utils.inc");
require_once("acme_gui.inc");

class HtmlList
{
	/*
		javascript 'events':
			<tableId>_row_added(tableId, rowNr)
			<tableId>_field_changed(tableId, rowNr, fieldId, field)
	*/

	public $tablename = "";
	public $fields = array();
	public $editmode = false;
	public $fields_details = null;
	public $keyfield = "";
	
	public function __construct($tablename, $fields) {
		$this->tablename = $tablename;
		$this->fields = $fields;
	}
	
	public function Draw($data) {
		return $this->acme_htmllist($data, $this->fields, $this->editmode, $this->fields_details);
	}
	
	public function outputjavascript() {
		$table_def = array();
		$table_def['keyfield'] = $this->keyfield;
		phparray_to_javascriptarray($table_def, "tabledefinition_".$this->tablename,Array('/*','/*/*'));
		phparray_to_javascriptarray($this->fields, "fields_".$this->tablename,Array('/*','/*/name','/*/type','/*/text','/*/size','/*/items','/*/items/*','/*/items/*/*','/*/items/*/*/name'));
		if (isset($this->fields_details) && is_array($this->fields_details) && count($this->fields_details) != 0) {
			phparray_to_javascriptarray($this->fields_details,"fields_details_".$this->tablename,Array('/*','/*/name','/*/columnheader','/*/description','/*/type','/*/text','/*/size','/*/items','/*/items/*','/*/items/*/*','/*/items/*/*/name','/*/items/*/*/name'));
		}
	}

	// function retrieves all posted values and returns an array
	public function acme_htmllist_get_values() {
		$values = array();

		$rowindexes = $_POST[$this->tablename."_rowindex"];
		if (is_array($rowindexes)) {
		foreach($rowindexes as $rowindex) {
			$x = $rowindex;
			$value = array();
			$add_item = false;
			if (is_array($this->fields_details)) {
				$fields = array_merge($this->fields, $this->fields_details);
			} else {
				$fields = $this->fields;
			}
			foreach($fields as $item) {
				$itemname = $item['name'];
				if (!empty($_POST[$this->tablename.$itemname.$x])) {
					$value[$itemname] = $_POST[$this->tablename.$itemname.$x];
					if ($item['type'] == 'textarea') {
						$value[$itemname] = base64_encode($value[$itemname]);
					}
				}
				$add_item |= isset($_POST[$this->tablename.$itemname.$x]);
			}
			if ($add_item) {
				if ($this->keyfield != "") {
					if (isset($_POST[$this->tablename."_key".$x])) {
						$key = $_POST[$this->tablename."_key".$x];
					} else {
						$key = $_POST[$this->tablename.$this->keyfield.$x];
					}
				} else {
					$key = "";
				}
				$index = $_POST[$this->tablename."_rowindex".$x];
				$value['_index'] = $index;
				if (isset($values[$key])) {
					$values[] = $value;
				} else {
					$values[$key] = $value;
				}
			}
		}
		}
		return $values;
	}
	
	function acme_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter) {
		$result = "";
		$itemnamenr = $this->tablename . $itemname . $counter;
		if ($editable) {
			switch ($item['type']) {
				case 'select':
					$result .= echo_html_select($itemnamenr,
									$item['items'],
									$itemvalue,
									"-none available-",
									"html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);",
									"");
					break;
				case 'checkbox':
					$checked = ($itemvalue == 'yes') ? " checked" : "";
					$result .= "<input onclick='html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);' name='{$itemnamenr}' id='{$itemnamenr}' type='checkbox'{$checked} value='yes' />";
					break;
				case 'textarea':
					$result .= "<textarea class=\"form-control nowrap\" name=\"{$itemnamenr}\" id=\"{$itemnamenr}\" cols=\"{$item['size']}\" rows=\"10\" style=\"width:100%\">";
					$result .= htmlspecialchars(base64_decode($itemvalue));
					$result .= "</textarea>";
					break;
				case 'fixedtext':
					$result .= $item['text'];
					break;
				case 'password':
					$itemvalue = htmlspecialchars($itemvalue, ENT_QUOTES);
					$result .= "<input class=\"form-control\" name=\"{$itemnamenr}\" id=\"{$itemnamenr}\" type=\"password\" autocomplete=\"new-password\" value=\"{$itemvalue}\" />";
					break;
				default:
					$itemvalue = htmlspecialchars($itemvalue, ENT_QUOTES);
					$result .= "<input class=\"form-control\" name=\"{$itemnamenr}\" id=\"{$itemnamenr}\" type=\"text\" value=\"{$itemvalue}\" />";
					break;
			}
		} else {
			switch ($item['type']) {
				case 'select':
					$result .= $item['items'][$itemvalue]['name'];
					break;
				case 'checkbox':
					$result .= ($itemvalue == 'yes') ? gettext('yes') : gettext('no');
					break;
				case 'textarea':
					$result .= "<div style=\"overlow:scroll;max-height:120px;max-width:{$item['colwidth']};overflow-y: scroll;\">";
					$result .= str_replace(" ","&nbsp;", str_replace("\n","<br/>", htmlspecialchars(base64_decode($itemvalue))));
					$result .= '</div>';
					break;
				case 'fixedtext':
					$result .= $item['text'];
					break;
				case 'password':
					$result .= '********';
					break;
				default:
					$result .= htmlspecialchars($itemvalue);
					break;
			}
			if ((stripos($item['name'], 'key') !== false) ||
			    (stripos($item['name'], 'secret') !== false) ||
			    (stripos($item['name'], 'sha256') !== false) ||
			    (stripos($item['name'], 'password') !== false) ||
			    (stripos($item['name'], 'token') !== false)) {
				$itemvalue = '********';
				$result = '********';
			}
		}
		return $result;
	}

	function acme_htmllist($rowvalues, $items, $editstate=false, $itemdetails=null) {
		$tablename = $this->tablename;
		global $g, $counter;
		$result = "";
		$result .= "
		<div class='panel panel-default'>
		<div class='panel-heading'>
			Table
		</div>
		<div class='table-responsive panel-body'>
			<table id='{$tablename}' class='table table-hover table-striped table-condensed'>
				<thead>
					<tr>
						<th><!-- checkbox --></th>";
		foreach($items as $item){
			$result .= "<th>{$item['columnheader']}</th>";
		}
		$result .= "<th>Actions</th>
		</tr>
		</thead>
		<tbody class='user-entries'>";
		if (is_array($rowvalues)) {
			foreach($rowvalues as $keyid => $value) {
				if (!empty($this->keyfield)) {
					if (preg_match("/[^0-9]/", $keyid)) {
						$itemvalue = $keyid;
					} else {
						$itemvalue = $value[$this->keyfield];
					}
					$key = "<input name='{$tablename}_key{$counter}' id='{$tablename}_key{$counter}' class='form-control hidden' value='{$itemvalue}'>";
				} else {
					$key = "";
				}
				
				if (!$editstate) {
					$result .= "<tr id='tr_view_$counter' ondblclick='editRow($counter); return false;' >
						<td>
							<input type='checkbox' id='frvc{$counter}' onClick='fr_toggle_two({$counter},this)' name='{$tablename}_row[]' value='{$counter}'/>
							<a class='fa-solid fa-anchor' id='Xmoveview_{$counter}' onClick='moveRowUpAboveAnchor({$counter}, \"{$tablename}\")' title='".gettext("Move checked entries to here")."'></a>
						</td>
				  ";

					$leftitem = true;
					foreach($items as $item) {
						$result .= "<td>";
						$itemname = $item['name'];
						$itemvalue = $value[$itemname];
						if (isset($item['customdrawcell'])) {
							$result .= $item['customdrawcell']($this, $item, $itemvalue, false, $itemname, $counter);
						} else {
							$result .= $this->acme_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
						}
						$result .= "</td>";
						$leftitem = false;
						
					}
					$result .= "
					<td class='action-icons'>
					<a onclick='editRow({$counter}); return false;'>".acmeicon('edit','edit entry')."</a>
					<a onclick='deleteRow({$counter}, \"{$tablename}\"); return false;'>".acmeicon('delete','delete entry')."</a>
					<a onclick='dupRow({$counter}, \"{$tablename}\"); return false;'>".acmeicon('clone','duplicate entry')."</a>
					";
					if (empty($this->noindex)) {
						$result .= "
						<a onclick='moveRowUp({$counter}, \"{$tablename}\"); return false;'>".acmeicon('moveup','move row up')."</a>
						<a onclick='moveRowDown({$counter}, \"{$tablename}\"); return false;'>".acmeicon('movedown','move row down')."</a>
						";
					}
					$result .= "
					</td>";
					$result .= "</tr>";
				}
				$displaystyle = $editstate ? "" : "hidden";
				$result .= "<tr id='tr_edit_$counter' class='$displaystyle'>
						<td>
							<input type='checkbox' id='frec{$counter}' onClick='fr_toggle_two({$counter},this)' name='{$tablename}_row[]' value='{$counter}'/>
							<a class='fa-solid fa-anchor' id='Xmove_{$counter}' onClick='moveRowUpAboveAnchor({$counter}, \"{$tablename}\")'  title='".gettext("Move checked entries to here")."'></a>
						</td>";
				foreach($items as $item){
					$itemname = $item['name'];
					$itemvalue = $value[$itemname];					
					$result .= "<td>".$key;
					if (isset($item['customdrawcell'])) {
						$result .= $item['customdrawcell']($this, $item, $itemvalue, true, $itemname, $counter);
					} else {
						$result .= $this->acme_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
					}
					$result .= "</td>";
					$key = "";
				}
				$result .= "
		<td class='action-icons'>
			<input name='{$tablename}_rowindex[]' id='{$tablename}_rowindex{$counter}' class='form-control hidden' value='{$counter}' />
			<a onclick='deleteRow({$counter}, \"{$tablename}\"); return false;' >".acmeicon('delete','delete entry')."</a>
			<a onclick='dupRow({$counter}, \"{$tablename}\"); return false;' >".acmeicon('clone','duplicate entry')."</a>
			";
			if (empty($this->noindex)) {
				$result .= "
				<a onclick='moveRowUp({$counter}, \"{$tablename}\"); return false;' >".acmeicon('moveup','move row up')."</a>
				<a onclick='moveRowDown({$counter}, \"{$tablename}\"); return false;' >".acmeicon('movedown','move row down')."</a>
				";
			}
			$result .= "
		</td>";
				$result .= "</tr>";
					if (isset($itemdetails)) {
						$displayviewstyle = $editstate ? "hidden" : "";
						$displayeditstyle = $editstate ? "" : "hidden";
						$colspan = count($items);
						$result .= "<tr id='tr_viewdetail_$counter'>";
						$result .= <<<EOT
							<td>
							<div class='$displayviewstyle' id="htmltable_{$tablename}_{$counter}_details_off" style="position:relative;float:right;width:11px;height:11px;">
							<a onclick="htmltable_toggle_details('{$tablename}','{$counter}','htmltable_{$tablename}_{$counter}_details')">
EOT
							. acmeicon('expand','Expand advanced server settings') . '</a></div></td>';
						$result .= "<td colspan='$colspan'>";
						$itemnr = 0;
						$result .= "<div id='htmltable_{$tablename}_{$counter}_details_view' class='$displayviewstyle'>";
						$leftitem = true;
						foreach($itemdetails as $item) {
							$itemname = $item['name'];
							$itemvalue = $value[$itemname];
							if ($this->fields_details_showfieldfunction != null) {
								// filter context un-related items through customizable function.
								$fn = &$this->fields_details_showfieldfunction;
								if ($fn($this, $itemname, $value) == false) {
									continue;
								}
							} else
							if (empty($itemvalue)) {
								continue;
							}
							$result .= "<div style='float: left;padding-right: 2px;'>";
							if (!$leftitem) {
								$result .= ", ";
							}
							$leftitem = false;
							$result .= $item['columnheader'] . ": ";
							if (isset($item['customdrawcell'])) {
								$result .= $item['customdrawcell']($this, $item, $itemvalue, false, $itemname, $counter);
							} else {
								$result .= $this->acme_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
							}
							$itemnr++;
							$result .= "</div>";
						}
						$result .= "</div>";
						$result .= "<div id='htmltable_{$tablename}_{$counter}_details_edit' class='$displayeditstyle'>";
						$result .= "<table class='table table-hover table-striped table-condensed' style='border-collapse:collapse' border='1' cellspacing='0' >";
						foreach($itemdetails as $item) {
							$itemname = $item['name'];
							$result .= "<tr id='tr_edititemdetails_{$counter}_{$itemname}'>";
							$result .= "<td style='border-right:0'>";
							$result .= "{$item['columnheader']}: ";
							$result .= "</td>";
							$result .= "<td style='border-left:0'>";
							$itemvalue = $value[$itemname];
							
							if (!empty($item['description'])) {
								$result .= "{$item['description']}<br/>";
							}
							if (isset($item['customdrawcell'])) {
								$result .= $item['customdrawcell']($this, $item, $itemvalue, true, $itemname, $counter);
							} else {
								$result .= $this->acme_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
							}
							$result .= "</td>";
							$result .= "</tr>";
						}
						$result .= "</table>";
						$result .= "</div>";
						$result .= "</td>";
						$result .= "<td>";
						$result .= "</td>";
						$result .= "</tr>";
					}
				$counter++;
			}
		}
		$result .= "</tbody>
		</table>
			<button class='btn btn-success addbtn' type='button' value='add another entry' 
			onclick='javascript:addRowTo(\"{$tablename}\"); return false;'
			><i class='fa-solid fa-plus icon-embed-btn'></i>Add</button>
		</div></div>";
		return $result;
	}
}

function sort_index(&$a, &$b) {
	// sort callback function, cannot be inside the object.
	if ($a['_index'] != $b['_index']) {
		return $a['_index'] > $b['_index'] ? 1 : -1;
	}
	return 0;
}

function acme_htmllist_js($tablename = "") {
	global $g;
?><script type="text/javascript">
	var theme = "<?=$g['theme']?>";
	
	function html_listitem_change(tableId, fieldId, rowNr, field) {
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, fieldId, rowNr, field);
		}
	}
	
	function htmllist_get_select_items(prefix, tableId) {
		var items;
		var items_name = prefix+"_"+tableId;
		items = eval("typeof "+items_name+" !== 'undefined' ? "+items_name+" : null");
		return items;
	}

	function createFieldHtml(tableId, field, rowId) {
		var result = "";
		if(field['type'] === 'textbox') {
			result="<input name='" + tableId + field['name'] + rowId +
			"' id='" + tableId + field['name'] + rowId +
			"'><\/input> ";
		} else if(field['type'] === 'password') {
			result="<input name='" + tableId + field['name'] + rowId +
			"' id='" + tableId + field['name'] + rowId +
			"' type='password'\/> ";
		} else if(field['type'] === 'textarea') {
			result="<textarea class='form-control nowrap' cols='" + field['size'] + "' rows='15' style='width:100%' name='" + tableId + field['name'] + rowId +
			"' id='" + tableId + field['name'] + rowId +
			"'><\/textarea> ";
		} else if(field['type'] === 'select') {
			var seltext = "";
			var fieldid = field['name'];
			var fn = window["htmllist_get_select_options"];
			fielditems = field['items'];
			if (typeof fn === 'function'){
				fielditems = htmllist_get_select_options(tableId, field['name'], fielditems);
			}
			for (var fieldvalueid in fielditems) {
				var fieldvalue = fielditems[fieldvalueid];
				seltext += "<option value='"+fieldvalueid+"'>"+fieldvalue['name']+"<\/option>";
			}
			
			result="<select name='" + tableId + field['name'] + rowId + 
			"' id='" + tableId + field['name'] + rowId + "' "+
			"onchange='html_listitem_change(\""+tableId+"\",\""+field['name']+"\",\""+rowId+"\",this);' " +
			">" + seltext + "<\/select> ";
		} else if(field['type'] === 'fixedtext') {
			result=field['text'];
		} else {
			result="<input type='checkbox' name='" + tableId + field['name'] + rowId +"'"+
			"id='" + tableId + field['name'] + rowId + "' "+
			"onclick='html_listitem_change(\""+tableId+"\",\""+field['name']+"\",\""+rowId+"\",this);' " +
			"value='yes'><\/input> ";
		}
		return result;
	}
	
	var addRowTo = (function() {
	return (function (tableId) {
		var d, tbody, tr, td, bgc, i, ii, j, type, seltext, items;
		var btable, btbody, btr, btd;
		var fieldcount;
		d = document;
		
		tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
		totalrows++;

		// create edit row fields
		items = htmllist_get_select_items('fields',tableId);
		fieldcount = 0;
		tr = d.createElement("tr");
		tr.setAttribute("id","tr_edit_" + totalrows);

		td = d.createElement("td");
		td.innerHTML = "<td>"+
				'<input type="checkbox" id="frec'+totalrows+'" onClick="fr_toggle_two('+totalrows+',this)" name="' + tableId + '_row[]" value="'+totalrows+'"/>'+
				'<a class="fa-solid fa-anchor" id="Xmove_'+totalrows+'" onClick="moveRowUpAboveAnchor('+totalrows+', \'' + tableId + '\')"  title=\"<?=gettext("Move checked entries to here")?>\"></a>'+
				"</td>";
		tr.appendChild(td);

		for (var i in items) {
			fieldhtml = createFieldHtml(tableId, items[i], totalrows);
			td = d.createElement("td");
			td.innerHTML = fieldhtml;
			tr.appendChild(td);
			fieldcount += 1;
		}
		td = d.createElement("td");
		td.setAttribute("class","action-icons");
		
		// Recreate the action icons.
		var actions = "";
		actions += '<input name="'+tableId+'_rowindex[]" id="'+tableId+'_rowindex'+totalrows+'" class="hidden" value="'+totalrows+'" />' +
			'<a onclick="deleteRow(' + totalrows + ", '" + tableId + "'); return false;\" ><?=acmeicon('delete','delete entry')?></a>";
		actions += '<a onclick=\"dupRow(' + totalrows + ", '" + tableId + "'); return false;\" ><?=acmeicon('clone','duplicate entry')?></a>";
		
		var tabledefinition = htmllist_get_select_items("tabledefinition", tableId);
		if (tabledefinition && tabledefinition['keyfield'] === "") {
			actions += '<a onclick=\"moveRowUp(' + totalrows + ", '" + tableId + "'); return false;\" ><?=acmeicon('moveup','move row up')?></a>";
			actions += '<a onclick=\"moveRowDown(' + totalrows + ", '" + tableId + "'); return false;\" ><?=acmeicon('movedown','move row down')?></a>";
		}
		td.innerHTML = actions;
		tr.appendChild(td);
		tbody.appendChild(tr); // add the edit row to the table
		
		// create viewdetail row
		detailitems = htmllist_get_select_items('fields_details',tableId);
		if (detailitems) {
			tr = d.createElement("tr");
			tr.setAttribute("id","tr_viewdetail_" + totalrows);
			td = d.createElement("td");
			tr.appendChild(td);
			td = d.createElement("td");
			td.setAttribute("colspan", fieldcount);
			vdiv = d.createElement("div");
			vdiv.setAttribute("id", "htmltable_<?=$tablename?>_" + totalrows + "_details_view");
			vdiv.setAttribute("class","hidden");
			td.appendChild(vdiv);
			ediv = d.createElement("div");
			ediv.setAttribute("id", "htmltable_<?=$tablename?>_" + totalrows + "_details_edit");
			ediv.setAttribute("class", "");

			table = d.createElement("table");
			table.setAttribute("class","table table-hover table-striped table-condensed");
			table.setAttribute("style","border-collapse:collapse");
			table.setAttribute("border","1");
			table.setAttribute("cellspacing","0");
			etbody = d.createElement("tbody");

			for (var i in detailitems) {
				field = detailitems[i];
				fieldhtml = createFieldHtml(tableId, field, totalrows);
				subtr = d.createElement("tr");
				subtr.setAttribute("id","tr_edititemdetails_" + totalrows + "_" + field['name']);
				subtd = d.createElement("td");
				subtd.setAttribute("style","border-right:0");
				subtd.innerHTML = field['columnheader'] + ": ";
				subtr.appendChild(subtd);
				subtd = d.createElement("td");
				subtd.setAttribute("style","border-left:0");
				if (field['description']) {
					fieldhtml = field['description'] + "<br/>" + fieldhtml;
				}
				subtd.innerHTML = fieldhtml;
				subtr.appendChild(subtd);
				etbody.appendChild(subtr);
			}
			table.appendChild(etbody);
			ediv.appendChild(table);
			td.appendChild(ediv);
			tr.appendChild(td);
			td = d.createElement("td");
			tr.appendChild(td);
			tbody.appendChild(tr);  // add the viewdetail row to the table
		}
		// show/hide conditional fields if applicable using a custom function.
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, "toggle_details", totalrows, null);
		}
		
		javascript_row_added = tableId + "_row_added";
		var fn = window[javascript_row_added];
		if (typeof fn === 'function'){
			fn(tableId, totalrows);
		}
	});
	})();
	function dupRow(rowId, tableId) {
		var dupEl;
		var newEl;
		addRowTo(tableId);
		items = htmllist_get_select_items('fields',tableId);
		for (var i in items) {
			dupEl = document.getElementById(tableId + items[i]['name'] + rowId);
			newEl = document.getElementById(tableId + items[i]['name'] + totalrows);
			if (dupEl && newEl)
				if(items[i]['type'] === 'checkbox')
					newEl.checked = dupEl.checked;
				else
					newEl.value = dupEl.value;
		}
		items = htmllist_get_select_items('fields_details', tableId);
		for (var i in items) {
			dupEl = document.getElementById(tableId + items[i]['name'] + rowId);
			newEl = document.getElementById(tableId + items[i]['name'] + totalrows);
			if (dupEl && newEl)
				if(items[i]['type'] === 'checkbox')
					newEl.value = dupEl.checked ? 'yes' : '';
				else
					newEl.value = dupEl.value;
		}
		javascript_row_added = tableId + "_row_added";
		var fn = window[javascript_row_added];
		if (typeof fn === 'function'){
			fn(tableId, totalrows);
		}

		// show/hide conditional fields if applicable using a custom function.
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, "toggle_details", totalrows, null);
		}
	}
	function editRow(num) {
		var trview = document.getElementById('tr_view_' + num);
		var tredit = document.getElementById('tr_edit_' + num);
		//trview.style.display='none';
		//tredit.style.display='';
		hideElement(trview, true);
		hideElement(tredit, false);
	}
	function deleteRow(rowId, tableId) {
		var view = document.getElementById("tr_view_" + rowId);
		var viewdetail = document.getElementById("tr_viewdetail_" + rowId);
		var edit = document.getElementById("tr_edit_" + rowId);
		if (view)
			view.parentNode.removeChild(view);
		if (viewdetail)
			viewdetail.parentNode.removeChild(viewdetail);
		if (edit)
			edit.parentNode.removeChild(edit);
	}

	function moveRowUp(rowId, tableId) {
	  moveRow(rowId, tableId, true);
	}
	function moveRowDown(rowId, tableId) {
	  moveRow(rowId, tableId, false);
	}
	function moveRow(rowId, tableId, up) {
		var rowview = document.getElementById("tr_view_" + rowId);
		var rowedit = document.getElementById("tr_edit_" + rowId);
		var rowviewdetail = document.getElementById("tr_viewdetail_" + rowId);
		
		var parent = rowedit.parentNode;
		var swapid;
		var swaprowedit;
		if (up){
			//move current rows before the previous row
			var prevtr;
			if (rowview) {
				prevtr = rowview.previousElementSibling;
			} else {
				prevtr = rowedit.previousElementSibling;
			}
			if (!prevtr)
				return; // was already top element.
			var swapid = prevtr['id'];
			var i = swapid.lastIndexOf('_');
			swapid = swapid.substr(i+1);
			var prevrowview = document.getElementById("tr_view_" + swapid);
			swaprowedit = document.getElementById("tr_edit_" + swapid);
			if (prevrowview){
				firstprevrow = prevrowview;
			} else {
				firstprevrow = swaprowedit;
			}
			// move the 3 rows
			if (rowview) {
				parent.insertBefore(rowview, firstprevrow);
			}
			parent.insertBefore(rowedit, firstprevrow);
			if (rowviewdetail) {
				parent.insertBefore(rowviewdetail, firstprevrow);
			}
		} else {
			//move next row before the current row
			var nexttr;
			if (rowviewdetail) {
				nexttr = rowviewdetail.nextElementSibling;
			} else {
				nexttr = rowedit.nextElementSibling;
			}
			if (!nexttr) {
				return; // was already bottom element.
			}
			var swapid = nexttr['id'];
			var i = swapid.lastIndexOf('_');
			swapid = swapid.substr(i+1);
			var prevrowview = document.getElementById("tr_view_" + swapid);
			swaprowedit = document.getElementById("tr_edit_" + swapid);
			var prevrowviewdetail = document.getElementById("tr_viewdetail_" + swapid);
			if (rowview){
				firstrow = rowview;
			} else {
				firstrow = rowedit;
			}
			// move the 3 rows
			if (prevrowview) {
				parent.insertBefore(prevrowview, firstrow);
			}
			parent.insertBefore(swaprowedit, firstrow);
			if (prevrowviewdetail) {
				parent.insertBefore(prevrowviewdetail, firstrow);
			}
		}
		
		//var id_a = document.getElementById(tableId+'_rowindex' + swapid);
		//var id_b = document.getElementById(tableId+'_rowindex' + rowId);
		//temp = id_a.value;
		//id_a.value = id_b.value;
		//id_b.value = temp;
	}
	
	function htmltable_toggle_details(tableId, rowNr, table_row_detail_id) {
		tredit = document.getElementById(table_row_detail_id+'_off');
		treditdetail = document.getElementById(table_row_detail_id+'_edit');
		trviewdetail = document.getElementById(table_row_detail_id+'_view');

		current_on = $(tredit).hasClass("hidden");

		hideElement(tredit, !current_on);
		hideElement(trviewdetail, !current_on);
		hideElement(treditdetail, current_on);

		// show/hide conditional fields if applicable using a custom function.
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, "toggle_details", rowNr, null);
		}
	}

	function fr_toggle_two(id, chk) {
		var ischecked = chk.checked;
		fr_toggle_checked(id, ischecked);
	}
	function fr_toggle_checked(id, checked) {
		var chboxe = $('#frec' + id);
		var chboxv = $('#frvc' + id);
		chboxe.prop('checked', checked);
		chboxv.prop('checked', checked);

		var editrow = $('#tr_edit_' + id);
		var viewrow = $('#tr_view_' + id);
		if (checked) {
			editrow.addClass('active');
			viewrow.addClass('active');
		} else {
			editrow.removeClass('active');
			viewrow.removeClass('active');
		}
	}

	function moveRowUpAboveAnchor(rowId, tableId) {
		var table = $('#'+tableId);
		var viewcheckboxes = $('[id^=frvc]input:checked', table);
		var editcheckboxes = $('[id^=frec]input:checked', table);
		var rowview = $("#tr_view_" + rowId, table);
		var rowedit = $("#tr_edit_" + rowId, table);
		var moveabove = rowview;
		if (moveabove.length === 0) {
			moveabove = rowedit;
		}
		//var parent = moveabove[0].parentNode;
		
		editcheckboxes.each(function( index ) {
			var moveid = this.value;
			console.log( index + ": " + this.id );

			var prevrowview = $("#tr_view_" + moveid, table);
			var swaprowedit = $("#tr_edit_" + moveid, table);
			var prevrowviewdetail = $("#tr_viewdetail_" + moveid, table);

			// move the 3 rows
			if (prevrowview) {
				prevrowview.insertBefore(moveabove);
			}
			swaprowedit.insertBefore(moveabove);
			if (prevrowviewdetail) {
				prevrowviewdetail.insertBefore(moveabove);
			}
			fr_toggle_checked(moveid, false);
		});

	}

function TEST(event){
	var editcheckboxes = $('[id^=frec]input:checked');
	alert(editcheckboxes.length);

}

events.push(function() {
	$('[id^=Xmoveview_]').click(function (event) {
		//TEST(event);
	});
	$('[id^=Xmove_]').click(function (event) {
		//TEST(event);
	});
	$('[id^=Xmove_]').css('cursor', 'pointer');

});

function hideElement(element, hide) {
	if (element) {
		if(hide) {
			$(element).addClass('hidden');
		} else {
			$(element).removeClass('hidden');
		}
	}
}

//<![CDATA[
//]]>
</script>

<?php
}

?>
