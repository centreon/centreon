<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

?>
<script language='javascript' type='text/javascript'>
function mk_pagination(){}
function mk_paginationFF(){}
function set_header_title(){}

var o = '<?php echo $o; ?>';
var _cmdId = '<?php echo $cmdId ?? null; ?>';
var _svcId = '<?php echo isset($cmdId) ? $service_id : null; ?>';
var _svcTplId = '<?php echo isset($cmdId) ? $serviceTplId : null; ?>';

/**
 * Escapes a value for safe insertion as HTML text/attribute content.
 */
function argEscapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : value;
    return div.innerHTML;
}

/**
 * Builds one .cf-field row for a single command argument, matching the
 * Host form's check-command-args pattern: floating label, and — when the
 * command defines an example value — an arrow that copies it into the
 * real field next to a disabled preview.
 */
function renderArgumentRow(arg) {
    var hasExample = arg.example !== '' && !arg.disabled;
    var html = '<div class="cf-row"><div class="cf-field" style="max-width:60%;">'
        + '<input type="text" placeholder="&nbsp;" value="' + argEscapeHtml(arg.value) + '" name="' + argEscapeHtml(arg.name) + '"'
        + (arg.disabled ? ' disabled' : '') + '>'
        + '<label class="cf-label-float">' + argEscapeHtml(arg.description) + '</label>';
    if (hasExample) {
        html += '<img src="./img/icons/arrow-left.png" class="ico-14" style="cursor:pointer;margin:0 6px;vertical-align:middle;order:10;" onclick="set_arg(\'example_' + arg.name + '\',\'' + arg.name + '\');">'
            + '<input type="text" disabled value="' + argEscapeHtml(arg.example) + '" name="example_' + arg.name + '" style="order:11;flex:0 0 140px;">';
    }
    html += '</div></div>';
    return html;
}

/**
 * Fetches the current command's arguments as JSON and renders them into
 * #dynamicDiv. Replaces the previous client-side XSLT transform (fetching
 * XML + a stylesheet and running them through the browser's XSLTProcessor)
 * with a plain AJAX call and JS templating — much easier to reason about
 * and to actually debug.
 */
function transformForm()
{
    jQuery.ajax({
        url: './include/configuration/configObject/service/xml/argumentsJson.php',
        data: { cmdId: _cmdId, svcId: _svcId, svcTplId: _svcTplId, o: o },
        dataType: 'json',
        success: function (data) {
            var target = document.getElementById('dynamicDiv');
            var header = document.getElementById('cf-args-header');
            var args = data.args || [];
            if (target) {
                var html = '';
                args.forEach(function (arg) {
                    html += renderArgumentRow(arg);
                });
                target.innerHTML = html;
            }
            if (header) {
                header.style.display = args.length ? 'flex' : 'none';
            }
        }
    });
    trapId = 0;
}

/**
 *
 */
function changeCommand(value)
{
    _cmdId = value;
    if(document.getElementById('svcTemplate') != null){
        _templateId = document.getElementById('svcTemplate').value;
    }
    transformForm();
}

/**
 *
 */
function changeServiceTemplate(value)
{
    _svcTplId = value;
    if(document.getElementById('checkCommand') != null){
        _cmdId = document.getElementById('checkCommand').value;
    }
    transformForm();
}
</script>
