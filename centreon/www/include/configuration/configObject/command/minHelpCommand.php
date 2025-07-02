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

require_once __DIR__ . '/minHelpCommandFunctions.php';

$commandId = filter_var(
    $_GET['command_id'] ?? $_POST['command_id'] ?? null,
    FILTER_VALIDATE_INT
);

$commandName = htmlspecialchars($_GET['command_name'] ?? $_POST['command_name'] ?? null);

if ($commandId !== false) {
    $commandLine = getCommandById($pearDB, (int) $commandId) ?? '';

    ['commandPath' => $commandPath, 'plugin' => $plugin, 'mode' => $mode] = getCommandElements($commandLine);

    $command = replaceMacroInCommandPath($pearDB, $commandPath);
} else {
    $command = $centreon->optGen['nagios_path_plugins'] . $commandName;
}

// Secure command
$search = ['#S#', '#BS#', '../', "\t"];
$replace = ['/', '\\', '/', ' '];
$command = str_replace($search, $replace, $command);

// Remove params
$explodedCommand = explode(' ', $command);
$commandPath = realpath($explodedCommand[0]) === false ? $explodedCommand[0] : realpath($explodedCommand[0]);

// Exec command only if located in allowed directories
$msg = 'Command not allowed';
if (isCommandInAllowedResources($pearDB, $commandPath)) {
    $command = $commandPath . ' ' . ($plugin ?? '') . ' ' . ($mode ?? '') . ' --help';
    $command = escapeshellcmd($command);
    $stdout = shell_exec($command . ' 2>&1');
    $msg = str_replace("\n", '<br />', $stdout);
}

$attrsText = ['size' => '25'];
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Plugin Help'));

// Command information
$form->addElement('header', 'information', _('Help'));
$form->addElement('text', 'command_line', _('Command Line'), $attrsText);
$form->addElement('text', 'command_help', _('Output'), $attrsText);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);

$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('command_line', CentreonUtils::escapeSecure($command, CentreonUtils::ESCAPE_ALL));
$tpl->assign('msg', CentreonUtils::escapeAllExceptSelectedTags($msg, ['br']));

$tpl->display('minHelpCommand.ihtml');
