<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 *
 */

session_start();
define('STEP_NUMBER', 4);

$_SESSION['step'] = STEP_NUMBER;

require_once __DIR__ . '/../steps/functions.php';
require_once __DIR__ . '/../../../bootstrap.php';

use Core\Migration\Application\Repository\ReadMigrationRepositoryInterface;

$template = getTemplate('templates');

/*
** Get and check initial Centreon version.
** Should be >= 2.8.0-beta1.
*/
$current = $_SESSION['CURRENT_VERSION'];
if (version_compare($current, '2.8.0-beta1') < 0) {
    $troubleshootTxt1 = _('Upgrade to this release requires Centreon >= 2.8.0-beta1.');
    $troubleshootTxt2 = sprintf(_('Your current version is %s.'), $current);
    $troubleshootTxt3 = _('Please upgrade to an intermediate release (ie. 2.8.x) first.');
    $contents .= sprintf(
        '<p class="required">%s<br/>%s<br/>%s</p>',
        $troubleshootTxt1,
        $troubleshootTxt2,
        $troubleshootTxt3
    );

/*
** Print upcoming database upgrade steps.
*/
} else {
    $contents = _('
        <p>
            Currently upgrading... please do not interrupt this process.
        </p>
    ');
    $contents .= "<table cellpadding='0' cellspacing='0' border='0' width='100%' class='StyleDottedHr' align='center'>
                    <thead>
                        <tr>
                            <th>" . _('Step') . "</th>
                            <th>" . _('Status') . "</th>
                        </tr>
                    </thead>
                    <tbody id='step_contents'>
                    </tbody>
                  </table>";

    $troubleshootTxt1 = _('You seem to be having trouble with your upgrade.');
    $troubleshootTxt2 = sprintf(
        _("Please check the \"upgrade.log\" and the \"sql-error.log\" located in \"%s\" for more details"),
        _CENTREON_LOG_
    );
    $troubleshootTxt3 = _('Refresh this page when the problem is fixed.');
    $contents .= sprintf(
        '<br/><p id="troubleshoot" style="display:none;">%s<br/><br/>%s<br/>%s</p>',
        $troubleshootTxt1,
        $troubleshootTxt2,
        $troubleshootTxt3
    );

    $kernel = \App\Kernel::createForWeb();

    $readMigrationRepository = $kernel->getContainer()->get(ReadMigrationRepositoryInterface::class);
    $migrations = $readMigrationRepository->findNewMigrations();

    $migration = array_shift($migrations);

    $migrationName = $migration ? $migration->getName() : '';
    $migrationModuleName = $migration ? $migration->getModuleName() : '';
    $migrationDescription = $migration ? $migration->getDescription() : '';
}

/*
** Generate template.
*/
$title = _('Installation');
$template->assign('step', STEP_NUMBER);
$template->assign('title', $title);
$template->assign('content', $contents);
$template->assign('blockPreview', 1);
$template->display('content.tpl');
?>
<script type='text/javascript'>
    let step = <?php echo STEP_NUMBER;?>;
    let result = false;
    let stepContent = jQuery('#step_contents');

    jQuery(function () {
        const migrationName = '<?php echo $migrationName;?>';
        const migrationModuleName = '<?php echo $migrationModuleName;?>';
        const migrationDescription = '<?php echo $migrationDescription;?>';
        jQuery("input[type=button]").hide();
        if (migrationName !== '') {
            nextStep(migrationName, migrationModuleName, migrationDescription);
        } else {
            generationCache();
        }
    });

    /**
     * Go to next upgrade script
     *
     * @param string name
     * @param string module_name
     * @return void
     */
    function nextStep(name, moduleName, description) {
        const uniqueName = `${moduleName}-${name}`;
        const shortModuleName = moduleName
            .replace(/^(centreon-)/,'')
            .replace(/(-server)$/,'');
        stepContent.append('<tr>');
        stepContent.append(`<td><b>[${shortModuleName}]</b> ${description}</td>`);
        stepContent.append(
            `<td class="install-step-status" name="${uniqueName}"><img src="../img/misc/ajax-loader.gif" /></td>`
        );
        stepContent.append('</tr>');
        doProcess(
            true,
            './step_upgrade/process/process_step' + step + '.php',
            {
                name,
                module_name: moduleName,
                description
            },
            function (response) {
                let data = jQuery.parseJSON(response);
                jQuery(`td[name="${uniqueName}"]`).html(data['msg']);
                if (data['result'] === 0) {
                    jQuery('#troubleshoot').hide();
                    if (data['name']) {
                        nextStep(data['name'], data['module_name'], data['description']);
                    } else {
                        generationCache();
                    }
                } else {
                    jQuery('#troubleshoot').show();
                    jQuery('#refresh').show();
                }
            });
    }

    function generationCache() {
      stepContent.append('<tr>');
      stepContent.append('<td>Application cache generation</td>');
      stepContent.append(
        '<td class="install-step-status" name="api.cache"><img src="../img/misc/ajax-loader.gif" /></td>'
      );
      stepContent.append('</tr>');
      doProcess(
        true,
        './steps/process/generationCache.php',
        null,
        function (response) {
          let data = jQuery.parseJSON(response);
          if (data['result'] === 0) {
            jQuery('td[name="api.cache"]').html("<span style='color:#88b917;'>" + data['msg'] + '</span>');
            jQuery('#troubleshoot').hide();
            jQuery('#next').show();
            result = true;
          } else {
            jQuery('td[name="api.cache"]').html("<span style='color:red;'>" + data['msg'] + '</span>');
            jQuery('#refresh').show();
          }
        });
    }

    /**
     * Validates info
     *
     * @return bool
     */
    function validation() {
        return result;
    }
</script>
