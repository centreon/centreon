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
 * Builds one .cf-field row for a single command argument, matching the
 * Host form's check-command-args pattern: floating label, and — when the
 * command defines an example value — an arrow that copies it into the
 * real field next to a disabled preview.
 *
 * Built through the DOM rather than by concatenating HTML: every value here
 * (argument value, macro name, macro description, command example) is user
 * supplied and reaches an attribute position. Assigning them with
 * setAttribute/textContent leaves no parsing context for them to escape from,
 * which string building cannot guarantee — the example arrow in particular
 * used to interpolate the macro name inside a JS string literal in an inline
 * onclick, where HTML escaping alone would not have been enough.
 */
function renderArgumentRow(arg)
{
    var hasExample = arg.example !== '' && !arg.disabled;
    var name = arg.name == null ? '' : String(arg.name);

    var row = document.createElement('div');
    row.className = 'cf-row';

    var field = document.createElement('div');
    field.className = 'cf-field';
    field.style.maxWidth = '60%';
    row.appendChild(field);

    var input = document.createElement('input');
    input.type = 'text';
    input.placeholder = '\u00a0';
    input.value = arg.value == null ? '' : arg.value;
    input.name = name;
    if (arg.disabled) {
        input.disabled = true;
    }
    field.appendChild(input);

    var label = document.createElement('label');
    label.className = 'cf-label-float';
    label.textContent = arg.description == null ? '' : arg.description;
    field.appendChild(label);

    if (hasExample) {
        var exampleName = 'example_' + name;

        var arrow = document.createElement('img');
        arrow.src = './img/icons/arrow-left.png';
        arrow.className = 'ico-14';
        arrow.style.cssText = 'cursor:pointer;margin:0 6px;vertical-align:middle;order:10;';
        arrow.addEventListener('click', function () {
            set_arg(exampleName, name);
        });
        field.appendChild(arrow);

        var example = document.createElement('input');
        example.type = 'text';
        example.disabled = true;
        example.value = arg.example;
        example.name = exampleName;
        example.style.cssText = 'order:11;flex:0 0 140px;';
        field.appendChild(example);
    }

    return row;
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
                target.innerHTML = '';
                args.forEach(function (arg) {
                    target.appendChild(renderArgumentRow(arg));
                });
            }
            if (header) {
                header.style.display = args.length ? 'flex' : 'none';
            }
        },
        // Without this the failure is invisible and destructive: #dynamicDiv is
        // empty server-side, so the ARGn inputs only exist once this call has
        // answered. Saving the form with none of them present makes
        // getCommandArgs() return null, and the stored arguments are wiped.
        // Surface it and keep the previous content rather than silently
        // offering an empty, submittable field set.
        error: function () {
            var message = (window.clI18n && window.clI18n.argumentsLoadError)
                || 'Command arguments could not be loaded. Reload the page before saving, otherwise the current arguments will be lost.';
            var target = document.getElementById('dynamicDiv');
            if (target) {
                // The rows on screen belong to the command that was selected before
                // this call: they are stale and must not be submitted against the new
                // one. Disable them and say so, rather than leaving them editable.
                target.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = true;
                });
                if (target.children.length === 0) {
                    var warning = document.createElement('div');
                    warning.className = 'cf-row';
                    warning.textContent = message;
                    target.appendChild(warning);
                }
            }
            if (typeof clToast === 'function') {
                clToast(message, 'error');
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
