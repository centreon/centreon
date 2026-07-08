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

require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

if (! $centreon->user->admin && $centreon->user->access->checkAction('generate_cfg') === 0) {
    require_once _CENTREON_PATH_ . 'www/include/core/errors/alt_error.php';

    return null;
}

// Get Poller List
$acl = $centreon->user->access;
$tab_nagios_server = $acl->getPollerAclConf(['get_row' => 'name', 'order' => ['name'], 'keys' => ['id'], 'conditions' => ['ns_activate' => 1]]);
// Sort the list of poller server
$pollersFromUrl = $_GET['poller'] ?? '';
$pollersId = explode(',', $pollersFromUrl);
$selectedPollers = [];

foreach ($tab_nagios_server as $key => $name) {
    if (in_array($key, $pollersId)) {
        $selectedPollers[] = ['id' => $key, 'text' => $name];
    }
}

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

$form->addElement('checkbox', 'debug', _('Run monitoring engine debug (-v)'), null, ['id' => 'ndebug']);
$form->addElement('checkbox', 'gen', _('Generate Configuration Files'), null, ['id' => 'ngen']);
$form->addElement('checkbox', 'move', _('Move Export Files'), null, ['id' => 'nmove']);
$form->addElement('checkbox', 'restart', _('Restart Monitoring Engine'), null, ['id' => 'nrestart']);
$form->addElement(
    'select',
    'restart_mode',
    _('Method'),
    [2 => _('Restart'), 1 => _('Reload')],
    ['id' => 'nrestart_mode', 'style' => 'width: 220px;']
);
$form->setDefaults(['debug' => '1', 'gen' => '1', 'move' => '1', 'restart' => '1', 'restart_mode' => '1']);

// Add multiselect for pollers
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_poller&action=list';
$attrPoller = ['datasourceOrigin' => 'ajax', 'allowClear' => true, 'availableDatasetRoute' => $route, 'multiple' => true];
$form->addElement('select2', 'nhost', _('Pollers'), ['class' => 'required'], $attrPoller);
$form->addRule('nhost', _('You need to select a least one polling instance.'), 'required', null, 'client');

$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);
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

$tpl->display('formGenerateFiles.ihtml');

?>
<script type='text/javascript'>
    var genInitPollers = <?php echo json_encode($selectedPollers); ?>;
    var genBase = './include/configuration/configGenerate/xml/';
    var genMsg = {
        move:     "<?php echo addslashes(_('Move files')); ?>",
        restart:  "<?php echo addslashes(_('Restart engine')); ?>",
        generate: "<?php echo addslashes(_('Generate & test')); ?>",
        postcmd:  "<?php echo addslashes(_('Post-command')); ?>",
        done:     "<?php echo addslashes(_('Configuration applied')); ?>",
        aborted:  "<?php echo addslashes(_('Finished with errors')); ?>",
        skippedPrev: "<?php echo addslashes(_('skipped (a previous step failed)')); ?>",
        noPostcmd:"<?php echo addslashes(_('Post-command — skipped (none configured)')); ?>"
    };

    jQuery(function () {
        for (var i = 0; i < genInitPollers.length; i++) {
            jQuery('#nhost').append(
                '<option value="' + genInitPollers[i].id + '" selected>' + genInitPollers[i].text + '</option>'
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

    function genPost(pid, token) {
        return new Promise(function (resolve) {
            jQuery.ajax({
                url: genBase + 'postcommand.php', type: 'POST', dataType: 'xml',
                data: { poller: pid, centreon_token: token },
                success: function (xml) {
                    var d = jQuery(xml);
                    var st = d.find('status').first().text();
                    resolve({ error: /NOK/i.test(st), status: st.replace(/<[^>]+>/g, '').trim(), err: '', debug: '' });
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

        var genOpt = jQuery('#ngen').is(':checked');
        var debugOpt = jQuery('#ndebug').is(':checked');
        var moveOpt = jQuery('#nmove').is(':checked');
        var restartOpt = jQuery('#nrestart').is(':checked');
        var mode = jQuery('#nrestart_mode').val();
        var token = jQuery('#centreon_token').val();

        // Safety: never move files or restart without a passing validation (-v).
        // If a deploy step is requested, force the test so it can gate the flow.
        if (moveOpt || restartOpt) { debugOpt = true; }

        var btn = document.getElementById('applyBtn');
        btn.disabled = true;
        ['generate', 'restart', 'postcmd'].forEach(function (s) { genStep(s, null); });
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
                tabsHtml += '<div class="gen-tab' + (idx === 0 ? ' active' : '') + '" id="gen-tab-' + p + '" onclick="genTabSwitch(\'' + p + '\')">'
                    + '<span class="st"></span>' + meta[p] + '</div>';
            }
            panelsHtml += '<div class="gen-console' + (showTabs ? ' attached' : '') + '" id="gen-log-' + p + '"'
                + (idx === 0 ? '' : ' style="display:none"') + '></div>';
        });
        document.getElementById('genTabs').innerHTML = tabsHtml;
        document.getElementById('genPanels').innerHTML = panelsHtml;

        // Which pollers have a post-command?
        var postPollers = [];
        try {
            var map = await jQuery.post(genBase + 'hasPostCommand.php', { poller: pollers.join(','), centreon_token: token });
            if (typeof map === 'string') { map = JSON.parse(map); }
            postPollers = pollers.filter(function (p) { return map && map[p]; });
        } catch (e) { postPollers = []; }

        // Visual groups -> substeps
        var groups = [];
        if (genOpt || debugOpt) {
            groups.push({ visual: 'generate', sub: [{ label: genMsg.generate, file: 'generateFiles.php', extra: { debug: debugOpt, generate: genOpt } }], pollers: pollers });
        }
        var mvrt = [];
        if (moveOpt) mvrt.push({ label: genMsg.move, file: 'moveFiles.php', extra: {} });
        if (restartOpt) mvrt.push({ label: genMsg.restart, file: 'restartPollers.php', extra: { mode: mode } });
        if (mvrt.length) groups.push({ visual: 'restart', sub: mvrt, pollers: pollers });
        if (postPollers.length) {
            groups.push({ visual: 'postcmd', sub: [{ label: genMsg.postcmd, post: true }], pollers: postPollers });
        }

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
                    var res = sub.post
                        ? await genPost(pid, token)
                        : await genXhr(sub.file, Object.assign({ poller: pid }, sub.extra || {}));
                    if (res.error) {
                        failed[pid] = true; anyError = true; groupFail++;
                        genTabState(pid, 'err');
                        genLog(pid, '<span class="err">&#10007; ' + (res.err || res.status || 'failed') + '</span>\n');
                        if (res.debug) { genLog(pid, '<span class="muted">' + res.debug + '</span>\n'); }
                    } else {
                        genTabState(pid, 'ok');
                        genLog(pid, '<span class="ok">&#10003;</span>' + (res.status ? ' <span class="muted">' + res.status + '</span>' : '') + '\n');
                    }
                    done++; genProgress(done, total);
                }
            }
            genStep(g.visual, groupFail > 0 ? 'err' : (groupRan === 0 ? 'skipped' : 'done'));
        }

        // Post-command step skipped (no poller has one configured)
        if (!postPollers.length) {
            genStep('postcmd', 'skipped');
            pollers.forEach(function (p) {
                if (!failed[p]) { genLog(p, '<span class="muted">' + genMsg.noPostcmd + '</span>\n'); }
            });
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
