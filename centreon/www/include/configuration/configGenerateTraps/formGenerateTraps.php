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

if (! isset($centreon)) {
    exit();
}

if (! $centreon->user->admin && $centreon->user->access->checkAction('generate_trap') === 0) {
    require_once _CENTREON_PATH_ . 'www/include/core/errors/alt_error.php';

    return null;
}

// Get Poller List
$acl = $centreon->user->access;
$tab_nagios_server = $acl->getPollerAclConf(['get_row' => 'name', 'order' => ['name'], 'keys' => ['id'], 'conditions' => ['ns_activate' => 1]]);

// A query string can carry any type (e.g. ?poller[]=1) while explode() only
// accepts a string: discard anything else instead of raising a TypeError.
$pollersFromUrl = is_string($_GET['poller'] ?? null) ? $_GET['poller'] : '';
$pollersId = explode(',', $pollersFromUrl);
$selectedPollers = [];

foreach ($tab_nagios_server as $key => $name) {
    if (in_array($key, $pollersId)) {
        $selectedPollers[] = ['id' => $key, 'text' => $name];
    }
}

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

$form->addElement('checkbox', 'generate', _('Generate trap database'), null, ['id' => 'ngenerate']);
$form->addElement('checkbox', 'apply', _('Apply configurations'), null, ['id' => 'napply']);
$form->addElement('checkbox', 'signal', _('Send signal to centreontrapd'), null, ['id' => 'nsignal']);
$form->addElement(
    'select',
    'signal_mode',
    _('Method'),
    [1 => _('Reload'), 2 => _('Restart')],
    ['id' => 'nsignal_mode', 'style' => 'width: 220px;']
);
$form->setDefaults(['generate' => '1', 'apply' => '1', 'signal' => '1', 'signal_mode' => '1']);

// Add select2 multiselect for pollers
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_poller&action=list';
$attrPoller = ['datasourceOrigin' => 'ajax', 'allowClear' => true, 'availableDatasetRoute' => $route, 'multiple' => true];
$form->addElement('select2', 'nhost', _('Pollers'), [], $attrPoller);
$form->addRule('nhost', _('You need to select a least one polling instance.'), 'required', null, 'client');

$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Needed to include the shared cl-/cf- framework translations (clI18n.ihtml).
$tpl->assign('centreon_path', _CENTREON_PATH_);
$csrfToken = createCSRFToken();

$tpl->assign('csrfToken', $csrfToken);
$tpl->assign('noPollerSelectedLabel', _('Compulsory Poller'));

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);

$tpl->display('formGenerateTraps.ihtml');

// JSON_INVALID_UTF8_SUBSTITUTE: a single non-UTF-8 byte in a poller name would
// otherwise make json_encode() return false, emitting an empty assignment that
// breaks the whole page script.
$initialPollersJson = json_encode($selectedPollers, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]';

?>
<script type='text/javascript'>
    var genInitPollers = <?php echo $initialPollersJson; ?>;
    var genBase = './include/configuration/configGenerateTraps/xml/';
    var genMsg = {
        generate: "<?php echo addslashes(_('Generate trap database')); ?>",
        apply:    "<?php echo addslashes(_('Apply configuration')); ?>",
        signal:   "<?php echo addslashes(_('Send signal')); ?>",
        done:     "<?php echo addslashes(_('Configuration applied')); ?>",
        aborted:  "<?php echo addslashes(_('Finished with errors')); ?>"
    };

    jQuery(function () {
        for (var i = 0; i < genInitPollers.length; i++) {
            // new Option() sets the label as text, so a poller name carrying
            // markup cannot break out of the option (same pattern as listing.js).
            jQuery('#nhost').append(
                new Option(genInitPollers[i].text, genInitPollers[i].id, true, true)
            );
        }
        jQuery('#nhost').trigger('change');

        // Turn the Advanced checkboxes into iPhone-style toggles
        document.querySelectorAll('.gen-adv-body input[type=checkbox]').forEach(function (input) {
            var wrapper = input.closest('.md-checkbox') || input;
            var toggle = document.createElement('label');
            toggle.className = 'cl-toggle';
            toggle.appendChild(input);
            var slider = document.createElement('span');
            slider.className = 'cl-toggle-slider';
            toggle.appendChild(slider);
            wrapper.parentNode.replaceChild(toggle, wrapper);
        });
    });

    function genLog(pid, html) {
        var c = document.getElementById('gen-log-' + pid);
        if (!c) return;
        c.innerHTML += html;
        c.scrollTop = c.scrollHeight;
    }

    function genProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 100;
        document.getElementById('genBar').style.width = pct + '%';
        document.getElementById('genPct').textContent = pct + '%';
    }

    function genStep(id, state) {
        var el = document.getElementById('stp-' + id);
        if (!el) return;
        el.classList.remove('active', 'done', 'err', 'skipped');
        if (state) el.classList.add(state);
    }

    // Restore both steps + connector (before recomputing which ones will run)
    function genResetStepper() {
        ['generate', 'apply'].forEach(function (s) {
            var e = document.getElementById('stp-' + s);
            if (e) { e.style.display = ''; }
        });
        var conns = document.querySelectorAll('.gen-stepper .gen-connector');
        for (var i = 0; i < conns.length; i++) { conns[i].style.display = ''; }
    }

    // Hide a step whose option is unchecked, along with one adjacent connector
    function genHideStep(id) {
        var el = document.getElementById('stp-' + id);
        if (!el) { return; }
        el.style.display = 'none';
        var next = el.nextElementSibling;
        if (next && next.classList.contains('gen-connector')) {
            next.style.display = 'none';
        } else {
            var prev = el.previousElementSibling;
            if (prev && prev.classList.contains('gen-connector')) { prev.style.display = 'none'; }
        }
    }

    function genTabState(pid, state) {
        var el = document.getElementById('gen-tab-' + pid);
        if (!el) return;
        el.classList.remove('run', 'ok', 'err');
        if (state) el.classList.add(state);
    }

    function genTabSwitch(pid) {
        jQuery('.gen-tab').removeClass('active');
        jQuery('#genPanels .gen-console').hide();
        jQuery('#gen-tab-' + pid).addClass('active');
        jQuery('#gen-log-' + pid).show();
    }

    function genXhr(file, data) {
        return new Promise(function (resolve) {
            jQuery.ajax({
                url: genBase + file, type: 'POST', dataType: 'xml', data: data,
                success: function (xml) {
                    var d = jQuery(xml);
                    resolve({
                        error: (d.find('statuscode').first().text() || '0') === '1',
                        status: d.find('status').first().text(),
                        err: d.find('error').first().text(),
                        debug: d.find('debug').first().text()
                    });
                },
                error: function () { resolve({ error: true, status: '', err: 'request failed', debug: '' }); }
            });
        });
    }

    async function genApply() {
        var pollers = jQuery('#nhost').val() || [];
        if (!pollers.length) {
            document.getElementById('noSelectedPoller').hidden = false;
            return;
        }
        document.getElementById('noSelectedPoller').hidden = true;

        var meta = {};
        jQuery('#nhost option:selected').each(function () { meta[this.value] = this.text; });

        var genOpt = jQuery('#ngenerate').is(':checked');
        var applyOpt = jQuery('#napply').is(':checked');
        var signalOpt = jQuery('#nsignal').is(':checked');
        var mode = jQuery('#nsignal_mode').val();
        var token = jQuery('#centreon_token').val();

        // At least one action must be selected.
        if (!genOpt && !applyOpt && !signalOpt) {
            document.getElementById('noSelectedAction').hidden = false;
            return;
        }
        document.getElementById('noSelectedAction').hidden = true;

        var btn = document.getElementById('applyBtn');
        btn.disabled = true;
        ['generate', 'apply'].forEach(function (s) { genStep(s, null); });
        // Only display the steps that will actually run (hide unchecked options)
        genResetStepper();
        if (!genOpt) { genHideStep('generate'); }
        if (!applyOpt && !signalOpt) { genHideStep('apply'); }
        var bar = document.getElementById('genBar');
        bar.style.background = 'var(--color-success, #88B922)';
        bar.style.width = '0%';
        document.getElementById('genPct').textContent = '0%';
        document.getElementById('console').style.display = 'block';

        // Build tabs (only if >1 poller) + one console panel per poller
        var showTabs = pollers.length > 1;
        var tabsHtml = '', panelsHtml = '';
        pollers.forEach(function (p, idx) {
            if (showTabs) {
                tabsHtml += '<div class="gen-tab' + (idx === 0 ? ' active' : '') + '" id="gen-tab-' + clEscapeAttr(p) + '" onclick="genTabSwitch(\'' + clEscapeAttr(p) + '\')">'
                    + '<span class="st"></span>' + clEscape(meta[p]) + '</div>';
            }
            panelsHtml += '<div class="gen-console' + (showTabs ? ' attached' : '') + '" id="gen-log-' + clEscapeAttr(p) + '"'
                + (idx === 0 ? '' : ' style="display:none"') + '></div>';
        });
        document.getElementById('genTabs').innerHTML = tabsHtml;
        document.getElementById('genPanels').innerHTML = panelsHtml;

        // Visual groups -> substeps ("Apply" and "Signal" share the same visual
        // step, like "Move & Restart" on the poller deploy page)
        var groups = [];
        if (genOpt) {
            groups.push({ visual: 'generate', sub: [{ label: genMsg.generate, file: 'generateTrapDb.php', extra: {} }], pollers: pollers });
        }
        var apSig = [];
        if (applyOpt) { apSig.push({ label: genMsg.apply, file: 'applyTraps.php', extra: {} }); }
        if (signalOpt) { apSig.push({ label: genMsg.signal, file: 'sendSignal.php', extra: { mode: mode } }); }
        if (apSig.length) { groups.push({ visual: 'apply', sub: apSig, pollers: pollers }); }

        var total = 0;
        groups.forEach(function (g) { total += g.sub.length * g.pollers.length; });
        var done = 0;
        var failed = {};
        var anyError = false;

        for (var gi = 0; gi < groups.length; gi++) {
            var g = groups[gi];
            genStep(g.visual, 'active');
            var groupRan = 0, groupFail = 0;
            for (var si = 0; si < g.sub.length; si++) {
                var sub = g.sub[si];
                for (var pi = 0; pi < g.pollers.length; pi++) {
                    var pid = g.pollers[pi];
                    // A previous step failed for this poller: skip without counting
                    // it as done, so the bar stops at the level actually reached.
                    if (failed[pid]) { continue; }
                    groupRan++;
                    genTabState(pid, 'run');
                    genLog(pid, '<b>' + sub.label + '</b>&#8230; ');
                    var res = await genXhr(sub.file, Object.assign({ poller: pid, centreon_token: token }, sub.extra || {}));
                    if (res.error) {
                        failed[pid] = true; anyError = true; groupFail++;
                        genTabState(pid, 'err');
                        // res.* carry server-side messages and raw command output:
                        // escape them before they reach the console's innerHTML.
                        genLog(pid, '<span class="err">&#10007; ' + clEscape(res.err || res.status || 'failed') + '</span>\n');
                        if (res.debug) { genLog(pid, '<span class="muted">' + clEscape(res.debug) + '</span>\n'); }
                    } else {
                        genTabState(pid, 'ok');
                        genLog(pid, '<span class="ok">&#10003;</span>' + (res.status ? ' <span class="muted">' + clEscape(res.status) + '</span>' : '') + '\n');
                    }
                    done++; genProgress(done, total);
                }
            }
            genStep(g.visual, groupFail > 0 ? 'err' : (groupRan === 0 ? 'skipped' : 'done'));
        }

        // Final line + bar colour
        genProgress(done, total);
        if (anyError) { bar.style.background = '#FF4A4A'; }
        pollers.forEach(function (p) {
            genLog(p, failed[p]
                ? '\n<span class="err"><b>' + genMsg.aborted + '</b></span>\n'
                : '\n<span class="ok"><b>' + genMsg.done + '</b></span>\n');
        });

        // Focus the first failed poller, if any
        if (showTabs) {
            for (var k = 0; k < pollers.length; k++) {
                if (failed[pollers[k]]) { genTabSwitch(pollers[k]); break; }
            }
        }
        btn.disabled = false;
    }
</script>
