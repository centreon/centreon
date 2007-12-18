<?php
/**
Centreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
Developped by : Cedrick Facon

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/

	if (!isset($oreon))
		exit();
?>
function getXhrC(){
	if(window.XMLHttpRequest) // Firefox et autres
	   var xhrC = new XMLHttpRequest();
	else if(window.ActiveXObject){ // Internet Explorer
	   try {
                var xhrC = new ActiveXObject("Msxml2.XMLHTTP");
            } catch (e) {
                var xhrC = new ActiveXObject("Microsoft.XMLHTTP");
            }
	}
	else { // XMLHttpRequest non support2 par le navigateur
	   alert("Votre navigateur ne supporte pas les objets XMLHTTPRequest...");
	   var xhrC = false;
	}
	return xhrC;
}

function addORdelTab(_name){

	var d = document.getElementsByName('next_check_case');
	if(d[0].checked == true)
	{
		_nc = 1;
	}
	else
	{
		_nc = 0;
	}
	monitoring_refresh();
}

function advanced_options(id){

	/// display hidden
	var d = document.getElementById(id);
	//var d1 = document.getElementById("advanced_1");


	if(d)
	{
		if (d.style.display == 'block') {
		d.style.display='none';
//		d1.style.display='block';
		}
		else
		{
		d.style.display='block';
//		d1.style.display='none';
		}
	}
	///
}

function construct_selecteList_ndo_instance(id){

	if(!document.getElementById("select_instance"))
	{
		var _select_instance = document.getElementById(id);

	//	_select_instance.innerHTML = "";

		var _select = document.createElement("select");
		_select.name = "select_instance";
		_select.id = "select_instance";


		_select.onchange = function() { _instance = this.value; _default_instance = this.selectedIndex; monitoring_refresh(); };


		var k = document.createElement('option');
		k.value= "ALL";
		var l = document.createTextNode("ALL");
		k.appendChild(l);
		_select.appendChild(k);


<?php
	include_once("./DBndoConnect.php");
	function get_ndo_instance_id($name_instance)
	{
		global $gopt,$pearDBndo;
		$rq = "SELECT instance_id FROM nagios_instances WHERE instance_name like '".$name_instance."'";
		$DBRESULT_NDO =& $pearDBndo->query($rq);
		$DBRESULT_NDO->fetchInto($ndo);
		return $ndo["instance_id"];
	}
	$DBRESULT =& $pearDB->query("SELECT cfg.instance_name as name FROM nagios_server ns, cfg_ndomod cfg WHERE cfg.ns_nagios_server = ns.id AND ns.ns_activate = 1");
	if (PEAR::isError($DBRESULT))
		print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";
	while($DBRESULT->fetchInto($nagios_server))
	{
	 	$isntance_id = get_ndo_instance_id($nagios_server["name"]);
?>
		var m = document.createElement('option');
		m.value= "<?=$isntance_id?>";
		_select.appendChild(m);
		var n = document.createTextNode("<?=$nagios_server["name"]?>");
		m.appendChild(n);
		_select.appendChild(m);
<?php
	}
?>
		_select.selectedIndex = _default_instance;
		_select_instance.appendChild(_select);

	}
}

function viewDebugInfo(_str){
	if(_debug)
	{
		_nb = _nb + 1;
		var mytable=document.getElementById("debugtable")
		var newrow=mytable.insertRow(0) //add new row to end of table
		var newcell=newrow.insertCell(0) //insert new cell to row
		newcell.innerHTML='<td>line:' + _nb + ' ' + _str + '</td>';
	}
}

function change_page(page_number){
viewDebugInfo('change page');
	_num = page_number;

	monitoring_refresh();
	pagination_changed();
	set_page(page_number);
}

function change_type_order(_type){
	if(_sort_type != _type){
		_sort_type = _type;
		monitoring_refresh();
	}
}

function change_order(_odr){

	if(_order == 'ASC'){
		_order = 'DESC';
	}
	else
		_order = 'ASC';
	monitoring_refresh();
}


function change_limit(l){
	_limit= l;
	pagination_changed();
	monitoring_refresh();
	var _sel1 = document.getElementById('l1');
	for(i=0;_sel1[i] && _sel1[i].value != l;i++)
		;
	_sel1.selectedIndex = i;
	set_limit(l);
}


var _numRows = 0;
//var _limit = 10;
//var _num = 0;

 function getVar (nomVariable)
 {
	 var infos = location.href.substring(location.href.indexOf("?")+1, location.href.length)+"&";
	 if (infos.indexOf("#")!=-1)
	 infos = infos.substring(0,infos.indexOf("#"))+"&";
	 var variable=''
	 {
		 nomVariable = nomVariable + "=";
		 var taille = nomVariable.length;
		 if (infos.indexOf(nomVariable)!=-1)
		 variable = infos.substring(infos.indexOf(nomVariable)+taille,infos.length).substring(0,infos.substring(infos.indexOf(nomVariable)+taille,infos.length).indexOf("&"))
	 }
	 return variable;
 }

function mk_img(_src, _alt)
{
	var _img = document.createElement("img");
  	_img.src = _src;
  	_img.alt = _alt;
  	_img.title = _alt;
	return _img;
}

function mk_pagination(resXML){
viewDebugInfo('mk pagination');

	var flag = 0;
	var infos = resXML.getElementsByTagName("i");

	if(infos[0]){
		var _nr = infos[0].getElementsByTagName("numrows")[0].firstChild.nodeValue;
		var _nl = infos[0].getElementsByTagName("limit")[0].firstChild.nodeValue;
		var _nn = infos[0].getElementsByTagName("num")[0].firstChild.nodeValue;

		if(_numRows != _nr){
			_numRows = _nr;
			flag = 1;
		}
		if(_num != _nn){
			_num = _nn;
			flag = 1;
		}
		if(_limit != _nl){
			_limit = _nl;
			flag = 1;
		}

		if(flag == 1){
		pagination_changed();
		}

	}
}

function pagination_changed(){
viewDebugInfo('pagination_changed');

	var page_max = 0;// Math.round( (_numRows / _limit) + 0.5);

if((_numRows % _limit) == 0)
{
	page_max =  Math.round( (_numRows / _limit));

}
else{
	page_max =  Math.round( (_numRows / _limit) + 0.5);
}

	if (_num >= page_max && _numRows && _num > 0)
	{
		viewDebugInfo('!!num!!'+_num);
		viewDebugInfo('!!max!!'+page_max);
		_num = page_max - 1;
		viewDebugInfo('new:'+_num);
		monitoring_refresh();
	}

	var p = getVar('p');
	var o = getVar('o');
	var search = '' + getVar('search');
	var _numnext = _num + 1;
	var _numprev = _num - 1;

	var _img_previous = mk_img("./img/icones/16x16/arrow_left_blue.gif", "previous");
	var _img_next = mk_img("./img/icones/16x16/arrow_right_blue.gif", "next");
	var _img_first = mk_img("./img/icones/16x16/arrow_left_blue_double.gif", "first");
	var _img_last = mk_img("./img/icones/16x16/arrow_right_blue_double.gif", "last");

	var _linkaction_right = document.createElement("a");
	_linkaction_right.href = '#' ;
	_linkaction_right.indice = _numnext;
	_linkaction_right.onclick=function(){change_page(this.indice)}
	_linkaction_right.appendChild(_img_next);

	var _linkaction_last = document.createElement("a");
	_linkaction_last.href = '#' ;
	_linkaction_last.indice = page_max - 1;
	_linkaction_last.onclick=function(){change_page(this.indice)}
	_linkaction_last.appendChild(_img_last);


	var _linkaction_first = document.createElement("a");
	_linkaction_first.href = '#' ;
	_linkaction_first.indice = 0;
	_linkaction_first.onclick=function(){change_page(this.indice)}
	_linkaction_first.appendChild(_img_first);


	var _linkaction_left = document.createElement("a");
	_linkaction_left.href = '#' ;
	_linkaction_left.indice = _numprev;
	_linkaction_left.onclick=function(){change_page(this.indice)}
	_linkaction_left.appendChild(_img_previous);


	var _pagination1 = document.getElementById('pagination1');
	var _pagination2 = document.getElementById('pagination2');


	_pagination1.innerHTML ='';
	if(_num > 0){
		_pagination1.appendChild(_linkaction_first);
		_pagination1.appendChild(_linkaction_left);
	}


	var istart = 0;
	for(i = 5, istart = _num; istart && i > 0 && istart > 0; i--)
	istart--;
	for(i2 = 0, iend = _num; ( iend <  (_numRows / _limit -1)) && ( i2 < (5 + i)); i2++)
		iend++;
	for (i = istart; i <= iend && page_max > 1; i++){
		var span_space = document.createElement("span");
		span_space.innerHTML = '&nbsp;';
		_pagination1.appendChild(span_space);

		var _linkaction_num = document.createElement("a");
  		_linkaction_num.href = '#' ;
  		_linkaction_num.indice = i;
  		_linkaction_num.onclick=function(){change_page(this.indice)};
		_linkaction_num.innerHTML = parseInt(i + 1);
		_linkaction_num.className = "otherPageNumber";
		if(i == _num)
		_linkaction_num.className = "currentPageNumber";
		_pagination1.appendChild(_linkaction_num);

		var span_space = document.createElement("span");
		span_space.innerHTML = '&nbsp;';
		_pagination1.appendChild(span_space);
	}

	if(_num < page_max - 1){
		_pagination1.appendChild(_linkaction_right);
		_pagination1.appendChild(_linkaction_last);
	}


	var _sel1 = document.getElementById('sel1');
	_sel1.innerHTML ='';

	var sel = document.createElement('select');
	sel.name = 'l';
	sel.id = 'l1';
	sel.onchange = function() { change_limit(this.value) };

	var _index = 0;
	for(i = 10; i <= 100 ;i += 10){
		if(i < _limit)
			_index++;
		var k = document.createElement('option');
		k.value= i;
		sel.appendChild(k);
		var l = document.createTextNode(i);
		k.appendChild(l);
	}
	sel.selectedIndex = _index;
	_sel1.appendChild(sel);
}

function escapeURI(La){
  if(encodeURIComponent) {
    return encodeURIComponent(La);
  }
  if(escape) {
    return escape(La)
  }
}

function mainLoop(){
  _currentInputFieldValue = document.getElementById('input_search').value;
  if( (_currentInputFieldValue.length >= 3 || _currentInputFieldValue.length == 0) && _oldInputFieldValue!=_currentInputFieldValue){
    var valeur=escapeURI(_currentInputFieldValue);
	_search = valeur;

	if(!_lock){
		monitoring_refresh();
		set_search(_search);
	}
  }
  _oldInputFieldValue=_currentInputFieldValue;
  setTimeout("mainLoop()",222);
}

function set_limit(limit)
{
	var xhrM = getXhrC();
	xhrM.open("POST","./include/monitoring/engine/set_session_history.php",true);
	xhrM.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	_var = "sid=<?php echo $sid;?>&limit="+limit+"&url=<?php echo $url;?>";
	xhrM.send(_var);
}
function set_search(search)
{
	var xhrM = getXhrC();
	xhrM.open("POST","./include/monitoring/engine/set_session_history.php",true);
	xhrM.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	_var = "sid=<?php echo $sid;?>&search="+search+"&url=<?php echo $url;?>";
	xhrM.send(_var);
}
function set_page(page)
{
	var xhrM = getXhrC();
	xhrM.open("POST","./include/monitoring/engine/set_session_history.php",true);
	xhrM.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	_var = "sid=<?php echo $sid;?>&page="+page+"&url=<?php echo $url;?>";
	xhrM.send(_var);
}