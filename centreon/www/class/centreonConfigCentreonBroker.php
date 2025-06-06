<?php

/*
 * Copyright 2005-2015 Centreon
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
 */

use App\Kernel;
use Centreon\Domain\Log\Logger;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

require_once _CENTREON_PATH_ . "www/class/centreon-config/centreonMainCfg.class.php";
require_once _CENTREON_PATH_ . 'www/include/common/vault-functions.php';

/**
 * Class
 *
 * @class CentreonConfigCentreonBroker
 * @description Class for Centreon Broker configuration
 */
class CentreonConfigCentreonBroker
{
    use VaultTrait;

    /** @var int */
    public $nbSubGroup = 1;
    /** @var array */
    public $arrayMultiple = [];
    /** @var CentreonDB */
    private $db;
    /** @var array */
    private $attrText = ["size" => "120"];
    /** @var array */
    private $attrInt = ["size" => "10", "class" => "v_number"];
    /** @var string */
    private $globalCommandFile = null;
    /** @var array<int|string,mixed>|null */
    private $tagsCache = null;
    /** @var array<int|string,mixed>|null */
    private $logsCache = null;
    /** @var array<int|string,string>|null */
    private $logsLevelCache = null;
    /** @var array<int,string>|null */
    private $typesCache = null;
    /** @var array<int,string>|null */
    private $typesNameCache = null;
    /** @var array */
    private $blockCache = [];
    /** @var array */
    private $fieldtypeCache = [];
    /** @var array */
    private $blockInfoCache = [];
    /** @var array */
    private $listValues = [];
    /** @var array */
    private $defaults = [];
    /** @var array */
    private $attrsAdvSelect = ["style" => "width: 270px; height: 70px;"];
    /** @var string */
    private $advMultiTemplate = '<table><tr>
        <td><div class="ams">{label_2}</div>{unselected}</td>
        <td align="center">{add}<br><br><br>{remove}</td>
        <td><div class="ams">{label_3}</div>{selected}</td>
        </tr></table>{javascript}';

    /**
     * CentreonConfigCentreonBroker construtor
     *
     * @param CentreonDB $db The connection to centreon database
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Serialize inner data
     * @return array
     */
    public function __sleep()
    {
        $this->db = null;
        return ['attrText', 'attrInt', 'tagsCache', 'typesCache', 'blockCache', 'blockInfoCache', 'listValues', 'defaults', 'fieldtypeCache'];
    }

    /**
     * Set the database
     *
     * @param CentreonDB $db The connection to centreon database
     */
    public function setDb($db): void
    {
        $this->db = $db;
    }

    /**
     * Return the list of tags
     *
     * @return array
     */
    public function getTags()
    {
        if (!is_null($this->tagsCache)) {
            return $this->tagsCache;
        }
        $query = "SELECT cb_tag_id, tagname
            FROM cb_tag
            ORDER BY tagname";
        try {
            $res = $this->db->query($query);
        } catch (PDOException $e) {
            return [];
        }
        $this->tagsCache = [];
        while ($row = $res->fetchRow()) {
            $this->tagsCache[$row['cb_tag_id']] = $row['tagname'];
        }
        return $this->tagsCache;
    }

    /**
     * Return the list of logs option
     *
     * @return array
     */
    public function getLogsOption()
    {
        if (!is_null($this->logsCache)) {
            return $this->logsCache;
        }
        $query = "SELECT log.`id`, log.`name` FROM `cb_log` log";
        try {
            $res = $this->db->query($query);
        } catch (PDOException $e) {
            return [];
        }
        $this->logsCache = [];
        while ($row = $res->fetchRow()) {
            $this->logsCache[$row['id']] = $row['name'];
        }
        return $this->logsCache;
    }

    /**
     * Return the list of logs level
     *
     * @return array
     */
    public function getLogsLevel()
    {
        if (!is_null($this->logsLevelCache)) {
            return $this->logsLevelCache;
        }
        $query = "SELECT `id`, `name` FROM `cb_log_level`";
        try {
            $res = $this->db->query($query);
        } catch (PDOException $e) {
            return [];
        }
        $this->logsLevelCache = [];
        while ($row = $res->fetchRow()) {
            $this->logsLevelCache[$row['id']] = $row['name'];
        }
        return $this->logsLevelCache;
    }

    /**
     * Get the tagname
     *
     * @param int $tagId The tag id
     * @return string|null null in error
     */
    public function getTagName($tagId)
    {
        if (!is_null($this->tagsCache) && isset($this->tagsCache[$tagId])) {
            return $this->tagsCache[$tagId];
        }
        $query = "SELECT tagname FROM cb_tag WHERE cb_tag_id = %d";
        try {
            $res = $this->db->query(sprintf($query, $tagId));
        } catch (PDOException $e) {
            return null;
        }
        $row = $res->fetch();
        if (is_null($row)) {
            return null;
        }
        return $row['tagname'];
    }

    /**
     * Get the typename
     *
     * @param int $typeId The type id
     * @return string|null null in error
     */
    public function getTypeShortname($typeId)
    {
        if (!is_null($this->typesCache) && isset($this->typesCache[$typeId])) {
            return $this->typesCache[$typeId];
        }
        $query = "SELECT type_shortname FROM cb_type WHERE cb_type_id = %d";
        try {
            $res = $this->db->query(sprintf($query, $typeId));
        } catch (PDOException $e) {
            return null;
        }
        $row = $res->fetch();
        if (is_null($row)) {
            return null;
        }
        $this->typesCache[$typeId] = $row['type_shortname'];
        return $this->typesCache[$typeId];
    }

    /**
     * Get the Centreon Broker type name
     *
     * @param int $typeId The type id
     * @return string|null null in error
     */
    public function getTypeName($typeId)
    {
        if (!is_null($this->typesNameCache) && isset($this->typesNameCache[$typeId])) {
            return $this->typesNameCache[$typeId];
        }
        $query = 'SELECT type_name FROM cb_type WHERE cb_type_id = %d';
        try {
            $res = $this->db->query(sprintf($query, $typeId));
        } catch (PDOException $e) {
            return null;
        }
        $row = $res->fetch();
        if (is_null($row)) {
            return null;
        }
        $this->typesNameCache[$typeId] = $row['type_name'];
        return $this->typesNameCache[$typeId];
    }

    /**
     * Return the list of config block
     *
     * The id is 'tag_id'_'type_id'
     * The name is "module_name - type_name"
     *
     * @param int $tagId The tag id
     * @return array
     */
    public function getListConfigBlock($tagId)
    {
        if (isset($this->blockCache[$tagId])) {
            return $this->blockCache[$tagId];
        }
        $query = "SELECT m.name, t.cb_type_id, t.type_name, ttr.cb_type_uniq
            FROM cb_module m, cb_type t, cb_tag_type_relation ttr
            WHERE m.cb_module_id = t.cb_module_id AND ttr.cb_type_id = t.cb_type_id AND ttr.cb_tag_id = %d";
        try {
            $res = $this->db->query(sprintf($query, $tagId));
        } catch (PDOException $e) {
            return [];
        }
        $this->blockCache[$tagId] = [];
        while ($row = $res->fetch()) {
            $name = $row['name'] . ' - ' . $row['type_name'];
            $id = $tagId . '_' . $row['cb_type_id'];
            $this->blockCache[$tagId][] = ['id' => $id, 'name' => $name, 'unique' => $row['cb_type_uniq']];
        }
        return $this->blockCache[$tagId];
    }

    /**
     * Create the HTML_QuickForm object with element for a block
     *
     * @param string $blockId The block id ('tag_id'_'type_id')
     * @param int $page The centreon page id
     * @param int $formId The form post
     * @param int $config_id
     * @return HTML_QuickFormCustom
     * @throws HTML_QuickForm_Error
     */
    public function quickFormById($blockId, $page, $formId = 1, $config_id = 0)
    {
        [$tagId, $typeId] = explode('_', $blockId);
        $fields = $this->getBlockInfos($typeId);
        $tag = $this->getTagName($tagId);
        $this->nbSubGroup = 1;

        $qf = new HTML_QuickFormCustom('form_' . $formId, 'post', '?p=' . $page);

        $qf->addElement(
            'text',
            $tag . '[' . $formId . '][name]',
            _('Name'),
            array_merge(
                $this->attrText,
                ['id' => $tag . '[' . $formId . '][name]', 'class' => 'v_required', 'onBlur' => "this.value = this.value.replace(/ /g, '_')"]
            )
        );

        $type = $this->getTypeShortname($typeId);
        $qf->addElement('hidden', $tag . '[' . $formId . '][type]');
        $qf->setDefaults([$tag . '[' . $formId . '][type]' => $type]);

        $typeName = $this->getTypeName($typeId);
        $qf->addElement('header', 'typeName', $typeName);

        $qf->addElement('hidden', $tag . '[' . $formId . '][blockId]');
        $qf->setDefaults([$tag . '[' . $formId . '][blockId]' => $blockId]);

        foreach ($fields as $field) {
            $parentGroup = "";
            $isMultiple = false;

            $elementName = $this->getElementName($tag, $formId, $field, $isMultiple);
            if ($isMultiple && $field['group'] !== '') {
                $displayNameGroup = "";
                $parentGroup = $this->getParentGroups($field['group'], $isMultiple, $displayNameGroup);
                $parentGroup = $parentGroup . "_" . $formId;
            }

            $elementType = null;
            $elementAttr = [];
            $default = null;
            $displayName = _($field['displayname']);
            switch ($field['fieldtype']) {
                case 'int':
                    $elementType = 'text';
                    $elementAttr = $this->attrInt;
                    if ($field['hook_name'] != '') {
                        $elementAttr = array_merge($elementAttr, ['onchange' => $field['hook_name'] . '.onChange(' . $field['hook_arguments'] . ')(this)', 'data-ontab-fn' => $field['hook_name'], 'data-ontab-arg' => $field['hook_arguments']]);
                    }
                    break;
                case 'select':
                    $elementType = 'select';
                    $elementAttr = $this->getListValues($field['id']);
                    $default = $this->getDefaults($field['id']);
                    break;
                case 'radio':
                    $tmpRadio = [];

                    if ($isMultiple && $parentGroup != "") {
                        $elementAttr = array_merge($elementAttr, ['parentGroup' => $parentGroup, 'displayNameGroup' => $displayNameGroup]);
                    }

                    if ($field['hook_name'] != '') {
                        $elementAttr = array_merge($elementAttr, ['onchange' => $field['hook_name'] . '.onChange(' . $field['hook_arguments'] . ')(this)', 'data-ontab-fn' => $field['hook_name'], 'data-ontab-arg' => $field['hook_arguments']]);
                    }

                    foreach ($this->getListValues($field['id']) as $key => $value) {
                        $elementAttr['id'] = uniqid('qf_' . $key . '_#index#');
                        $tmpRadio[] = $qf->createElement(
                            'radio',
                            $field['fieldname'],
                            null,
                            _($value),
                            $key,
                            $elementAttr
                        );
                    }
                    $qf->addGroup($tmpRadio, $elementName, _($field['displayname']), '&nbsp;');
                    $default = $this->getDefaults($field['id']);
                    break;
                case 'password':
                    $elementType = 'password';
                    $elementAttr = $this->attrText;
                    break;
                case 'multiselect':
                    $displayName = [_($field['displayname']), _("Available"), _("Selected")];
                    $elementType = 'advmultiselect';
                    $elementAttr = $this->getListValues($field['id']);
                    break;
                case 'text':
                default:
                    $elementType = 'text';
                    $elementAttr = $this->attrText;
                    break;
            }

            // If get information for read-only in database
            if (!is_null($field['value']) && $field['value'] !== false) {
                $elementType = null;
                $roValue = $this->getInfoDb($field['value']);
                $field['value'] = $roValue;
                if (is_array($roValue)) {
                    $qf->addElement('select', $elementName, $displayName, $roValue);
                } else {
                    $qf->addElement('text', $elementName, $displayName, $this->attrText);
                }
                $qf->freeze($elementName);
            }

            // Add required informations
            if ($field['required'] && is_null($field['value']) && $elementType != 'select') {
                $elementAttr = array_merge($elementAttr, ['id' => $elementName, 'class' => 'v_required']);
            }

            $elementAttrSelect = [];
            if ($isMultiple && $parentGroup != "") {
                if ($elementType != 'select') {
                    $elementAttr = array_merge($elementAttr, ['parentGroup' => $parentGroup, 'displayNameGroup' => $displayNameGroup]);
                    if ($field['hook_name'] != '') {
                        $elementAttr = array_merge($elementAttr, ['onchange' => $field['hook_name'] . '.onChange(' . $field['hook_arguments'] . ')(this)', 'data-ontab-fn' => $field['hook_name'], 'data-ontab-arg' => $field['hook_arguments']]);
                    }
                } else {
                    $elementAttrSelect = ['parentGroup' => $parentGroup, 'displayNameGroup' => $displayNameGroup];
                    if ($field['hook_name'] != '') {
                        $elementAttrSelect = array_merge($elementAttrSelect, ['onchange' => $field['hook_name'] . '.onChange(' . $field['hook_arguments'] . ')(this)', 'data-ontab-fn' => $field['hook_name'], 'data-ontab-arg' => $field['hook_arguments']]);
                    }
                }
            }

            // Add elements
            if (!is_null($elementType)) {
                if ($elementType == 'advmultiselect') {
                    $el = $qf->addElement(
                        $elementType,
                        $elementName,
                        $displayName,
                        $elementAttr,
                        $this->attrsAdvSelect,
                        SORT_ASC
                    );
                    $el->setButtonAttributes('add', ['value' => _("Add"), "class" => "btc bt_success"]);
                    $el->setButtonAttributes('remove', ['value' => _("Remove"), "class" => "btc bt_danger"]);
                    $el->setElementTemplate($this->advMultiTemplate);
                } else {
                    $el = $qf->addElement($elementType, $elementName, $displayName, $elementAttr, $elementAttrSelect);
                }
            }

            // Defaults values
            if (!is_null($field['value']) && $field['value'] !== false) {
                if ($field['fieldtype'] != 'radio') {
                    $qf->setDefaults([$elementName => $field['value']]);
                } else {
                    $qf->setDefaults([$elementName . '[' . $field['fieldname'] . ']' => $field['value']]);
                }
            } elseif (!is_null($default)) {
                if ($field['fieldtype'] != 'radio') {
                    $qf->setDefaults([$elementName => $default]);
                } else {
                    $qf->setDefaults([$elementName . '[' . $field['fieldname'] . ']' => $default]);
                }
            }
        }
        return $qf;
    }

    /**
     * Generate Cdata tag
     *
     * @return void
     * @throws Exception
     */
    public function generateCdata(): void
    {
        $cdata = CentreonData::getInstance();
        if (isset($this->arrayMultiple)) {
            foreach ($this->arrayMultiple as $key => $multipleGroup) {
                ksort($multipleGroup);
                $cdata->addJsData('clone-values-' . $key, htmlspecialchars(
                    json_encode($multipleGroup),
                    ENT_QUOTES
                ));
                $cdata->addJsData('clone-count-' . $key, count($multipleGroup));
            }
        }
    }

    /**
     * Get informations for a block
     *
     * @param int $typeId The type id
     *
     * @return array|false
     */
    public function getBlockInfos($typeId)
    {
        if (isset($this->blockInfoCache[$typeId])) {
            return $this->blockInfoCache[$typeId];
        }

        // Get the list of fields for a block
        $fields = [];
        $query = <<<'SQL'
            SELECT field.cb_field_id, field.fieldname, field.displayname, field.fieldtype, field.description, field.external,
                   field.cb_fieldgroup_id, field_grp.groupname,
                   field_rel.is_required, field_rel.order_display, field_rel.jshook_name, field_rel.jshook_arguments
            FROM cb_field field
            INNER JOIN cb_type_field_relation field_rel
                    ON field_rel.cb_field_id = field.cb_field_id
                AND (field_rel.cb_type_id = :type_id
                    OR field_rel.cb_type_id IN (
                        SELECT cb_type_id
                        FROM cb_type type
                        INNER JOIN cb_module_relation module_rel
                            ON module_rel.cb_module_id = type.cb_module_id
                            AND module_rel.inherit_config = 1
                            AND type.cb_module_id IN (
                                SELECT module_depend_id
                                FROM cb_module_relation module_rel2
                                INNER JOIN cb_type type2
                                    ON type2.cb_module_id = module_rel2.cb_module_id
                                    AND module_rel2.inherit_config = 1
                                    AND type2.cb_type_id = :type_id
                            )
                    )
               )
            LEFT JOIN cb_fieldgroup field_grp
                ON field_grp.cb_fieldgroup_id = field.cb_fieldgroup_id
                AND field_grp.multiple = 1
            ORDER BY field_rel.order_display;
            SQL;

        try {
            $statement = $this->db->prepare($query);
            $statement->bindValue(':type_id', $typeId, PDO::PARAM_INT);
            $statement->execute();
        } catch (PDOException $e) {
            return false;
        }
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $field = [];
            $field['id'] = $row['cb_field_id'];
            $field['fieldname'] = $row['fieldname'];
            $field['displayname'] = $row['displayname'];
            $field['fieldtype'] = $row['fieldtype'];
            $field['description'] = $row['description'];
            $field['required'] = $row['is_required'];
            $field['order'] = $row['order_display'];
            $field['group'] = $row['cb_fieldgroup_id'];
            $field['group_name'] = $row['groupname'];
            $field['hook_name'] = $row['jshook_name'];
            $field['hook_arguments'] = $row['jshook_arguments'];
            $field['value'] = !is_null($row['external']) && $row['external'] != '' ? $row['external'] : null;
            $fields[] = $field;
        }
        usort($fields, [$this, 'sortField']);
        $this->blockInfoCache[$typeId] = $fields;
        return $this->blockInfoCache[$typeId];
    }

    /**
     * Return a cb type id for the shortname given
     *
     * @param string $typeName
     *
     * @return int|null
     * @throws PDOException
     */
    public function getTypeId($typeName)
    {
        $typeId = null;

        $queryGetType = "SELECT cb_type_id FROM cb_type WHERE type_shortname = '$typeName'";
        $res = $this->db->query($queryGetType);

        if ($res) {
            while ($row = $res->fetch()) {
                $typeId = $row['cb_type_id'];
            }
        }

        return $typeId;
    }

    /**
     * @param array<string,mixed> $values
     * @return string[]
     */
    private function getColumnNamesForQuery(array $values): array
    {
        $columnNames = [
            'config_name',
            'config_filename',
            'ns_nagios_server',
            'config_activate',
            'daemon',
            'cache_directory',
            'event_queue_max_size',
            'event_queues_total_size',
            'command_file',
            'pool_size',
            'log_directory',
            'log_filename',
        ];
        if (isset($values['write_timestamp']['write_timestamp'])) {
            $columnNames[] = 'config_write_timestamp';
        }
        if (isset($values['write_thread_id']['write_thread_id'])) {
            $columnNames[] = 'config_write_thread_id';
        }
        if (isset($values['stats_activate']['stats_activate'])) {
            $columnNames[] = 'stats_activate';
        }
        if (isset($values['log_max_size'])) {
            $columnNames[] = 'log_max_size';
        }
        if (isset($values['bbdo_version'])) {
            $columnNames[] = 'bbdo_version';
        }

        return $columnNames;
    }

    /**
     * Insert a configuration into the database
     *
     * @param array $values The post array
     * @return bool
     */
    public function insertConfig(array $values): bool
    {
        $objMain = new CentreonMainCfg();
        // Insert the Centreon Broker configuration
        $columnNames = $this->getColumnNamesForQuery($values);

        $query = 'INSERT INTO cfg_centreonbroker ('
            . implode(', ', $columnNames)
            . ') VALUES (:'
            . implode(', :', $columnNames)
            . ')';

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':config_name', $values['name'], PDO::PARAM_STR);
            $stmt->bindValue(':config_filename', $values['filename'], PDO::PARAM_STR);
            $stmt->bindValue(':ns_nagios_server', $values['ns_nagios_server'], PDO::PARAM_STR);
            $stmt->bindValue(':config_activate', $values['activate']['activate'], PDO::PARAM_STR);
            $stmt->bindValue(':daemon', $values['activate_watchdog']['activate_watchdog'], PDO::PARAM_STR);
            $stmt->bindValue(':cache_directory', $values['cache_directory'], PDO::PARAM_STR);
            $stmt->bindValue(':log_directory', $values['log_directory'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':log_filename', $values['log_filename'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(
                ':event_queue_max_size',
                (int) $this->checkEventMaxQueueSizeValue($values['event_queue_max_size']),
                PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':event_queues_total_size',
                ! empty($values['event_queues_total_size']) ? (int) $values['event_queues_total_size'] : null,
                PDO::PARAM_INT
            );
            $stmt->bindValue(':command_file', $values['command_file'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(
                ':pool_size',
                ! empty($values['pool_size']) ? (int) $values['pool_size'] : null,
                PDO::PARAM_INT
            );
            if (in_array('config_write_timestamp', $columnNames)) {
                $stmt->bindValue(
                    ':config_write_timestamp',
                    $values['write_timestamp']['write_timestamp'],
                    PDO::PARAM_STR
                );
            }
            if (in_array('config_write_thread_id', $columnNames)) {
                $stmt->bindValue(
                    ':config_write_thread_id',
                    $values['write_thread_id']['write_thread_id'],
                    PDO::PARAM_STR
                );
            }
            if (in_array('stats_activate', $columnNames)) {
                $stmt->bindValue(':stats_activate', $values['stats_activate']['stats_activate'], PDO::PARAM_STR);
            }
            if (in_array('log_max_size', $columnNames)) {
                $stmt->bindValue(':log_max_size', $values['log_max_size'], PDO::PARAM_INT);
            }
            if (in_array('bbdo_version', $columnNames)) {
                $stmt->bindValue(':bbdo_version', $values['bbdo_version'], PDO::PARAM_STR);
            }

            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        $iIdServer = $values['ns_nagios_server'];
        $iId = $objMain->insertServerInCfgNagios(-1, $iIdServer, $values['name']);
        if (!empty($iId)) {
            $objMain->insertDefaultCfgNagiosLogger($iId);
        }

        /*
         * Get the ID
         */
        $query = "SELECT config_id FROM cfg_centreonbroker WHERE config_name = :config_name";
        try {
            $statement = $this->db->prepare($query);
            $statement->bindValue(':config_name', $values['name'], PDO::PARAM_STR);
            $statement->execute();
        } catch (PDOException $e) {
            return false;
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $id = $row['config_id'];

        /*
         * Log
         */
        $logs = $this->getLogsOption();
        $queryLog = "INSERT INTO cfg_centreonbroker_log (id_centreonbroker, id_log, id_level) VALUES ";
        foreach (array_keys($logs) as $logId) {
            $queryLog .= '(:id_centreonbroker, :log_' . $logId . ', :level_' . $logId . '), ';
        }
        $queryLog = rtrim($queryLog, ', ');
        try {
            $stmt = $this->db->prepare($queryLog);
            $stmt->bindValue(':id_centreonbroker', (int) $id, PDO::PARAM_INT);
            foreach ($logs as $logId => $logName) {
                $stmt->bindValue(':log_' . $logId, (int) $logId, PDO::PARAM_INT);
                $logValue = $values['log_' . $logName] ?? null;
                $stmt->bindValue(':level_' . $logId, (int) $logValue, PDO::PARAM_INT);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        try {
            $this->updateCentreonBrokerInfosByAPI($id, $values);
        } catch (Throwable $th) {
            error_log((string) $th);
            echo "<div class='msg' align='center'>" . _($th->getMessage()) . "</div>";

            return false;
        }
        return true;
    }

    /**
     * Update configuration
     *
     * @param int $id The configuration id
     * @param array $values The post array
     *
     * @return bool
     * @throws PDOException
     */
    public function updateConfig(int $id, array $values)
    {
        // Insert the Centreon Broker configuration
        $query = "";
        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                    UPDATE cfg_centreonbroker SET
                        config_name = :config_name,
                        config_filename = :config_filename,
                        ns_nagios_server = :ns_nagios_server,
                        config_activate = :config_activate,
                        daemon = :daemon,
                        config_write_timestamp = :config_write_timestamp,
                        config_write_thread_id = :config_write_thread_id,
                        stats_activate = :stats_activate,
                        cache_directory = :cache_directory,
                        event_queue_max_size = :event_queue_max_size,
                        event_queues_total_size = :event_queues_total_size,
                        command_file = :command_file,
                        log_directory = :log_directory,
                        log_filename = :log_filename,
                        log_max_size = :log_max_size,
                        pool_size = :pool_size,
                        bbdo_version = :bbdo_version
                    WHERE config_id = :config_id
                    SQL
            );
            $stmt->bindValue(':config_id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':config_name', $values['name'], PDO::PARAM_STR);
            $stmt->bindValue(':config_filename', $values['filename'], PDO::PARAM_STR);
            $stmt->bindValue(':ns_nagios_server', $values['ns_nagios_server'], PDO::PARAM_STR);
            $stmt->bindValue(':config_activate', $values['activate']['activate'], PDO::PARAM_STR);
            $stmt->bindValue(':daemon', $values['activate_watchdog']['activate_watchdog'], PDO::PARAM_STR);
            $stmt->bindValue(':config_write_timestamp', $values['write_timestamp']['write_timestamp'], PDO::PARAM_STR);
            $stmt->bindValue(':config_write_thread_id', $values['write_thread_id']['write_thread_id'], PDO::PARAM_STR);
            $stmt->bindValue(':stats_activate', $values['stats_activate']['stats_activate'], PDO::PARAM_STR);
            $stmt->bindValue(':cache_directory', $values['cache_directory'], PDO::PARAM_STR);
            $stmt->bindValue(':log_directory', $values['log_directory'], PDO::PARAM_STR);
            $stmt->bindValue(':log_filename', $values['log_filename'], PDO::PARAM_STR);
            $stmt->bindValue(':log_max_size', $values['log_max_size'], PDO::PARAM_INT);
            $stmt->bindValue(':bbdo_version', $values['bbdo_version'], PDO::PARAM_STR);
            $stmt->bindValue(
                ':event_queue_max_size',
                (int)$this->checkEventMaxQueueSizeValue($values['event_queue_max_size']),
                PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':event_queues_total_size',
                ! empty($values['event_queues_total_size']) ? (int) $values['event_queues_total_size'] : null,
                PDO::PARAM_INT
            );
            $stmt->bindValue(':command_file', $values['command_file'], PDO::PARAM_STR);
            empty($values['pool_size'])
                ? $stmt->bindValue(':pool_size', null, PDO::PARAM_NULL)
                : $stmt->bindValue(':pool_size', (int)$values['pool_size'], PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        /*
         * Log
         */
        $logs = $this->getLogsOption();
        $deleteStmt = $this->db->prepare(
            <<<'SQL'
                DELETE FROM cfg_centreonbroker_log WHERE id_centreonbroker = :config_id
                SQL
        );
        $deleteStmt->bindValue(':config_id', $id, PDO::PARAM_INT);
        $deleteStmt->execute();

        $queryLog = "INSERT INTO cfg_centreonbroker_log (id_centreonbroker, id_log, id_level) VALUES ";
        foreach (array_keys($logs) as $logId) {
            $queryLog .= '(:id_centreonbroker, :log_' . $logId . ', :level_' . $logId . '), ';
        }
        $queryLog = rtrim($queryLog, ', ');
        try {
            $stmt = $this->db->prepare($queryLog);
            $stmt->bindValue(':id_centreonbroker', (int) $id, PDO::PARAM_INT);
            foreach ($logs as $logId => $logName) {
                $stmt->bindValue(':log_' . $logId, (int) $logId, PDO::PARAM_INT);
                $stmt->bindValue(':level_' . $logId, (int) $values['log_' . $logName], PDO::PARAM_INT);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        try {
            $this->updateCentreonBrokerInfosByAPI($id, $values);
        } catch (Throwable $th) {
            error_log((string) $th);
            echo "<div class='msg' align='center'>" . _($th->getMessage()) . "</div>";

            return false;
        }

        return true;
    }

    /**
     * Find a broker config original value based on fieldIndex
     *
     * @param int $configId
     * @param string $configKey
     * @param int $fieldIndex
     * @param int $configGroupId
     *
     * @return string|null
     * @throws PDOException
     */
    private function findOriginalValueWithFieldIndex(int $configId, string $configKey, int $fieldIndex, int $configGroupId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT config_value FROM cfg_centreonbroker_info
            WHERE config_id = :configId
            AND config_key = :configKey
            AND fieldIndex = :fieldIndex
            AND config_group_id = :configGroupId'
        );

        $stmt->bindValue(':configId', $configId, \PDO::PARAM_INT);
        $stmt->bindValue(':configKey', $configKey, \PDO::PARAM_STR);
        $stmt->bindValue(':fieldIndex', $fieldIndex, \PDO::PARAM_INT);
        $stmt->bindValue(':configGroupId', $configGroupId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row['config_value'] ?? null;
    }

    /**
     * Retrieve lua original password if it hasn't change
     *
     * @param int $configId
     * @param array<string,mixed> $values
     *
     * @throws PDOException
     */
    private function revealLuaPasswords(int $configId, array &$values): void
    {
        foreach ($values['output'] as $configGroupId => &$output) {
            foreach (array_keys($output) as $key) {
                if (
                    preg_match('/^lua_parameter__value_(\\d+)$/', (string) $key, $matches) === 1
                    && array_key_exists("lua_parameter__value_{$matches[1]}", $output)
                    && $output["lua_parameter__value_{$matches[1]}"] === CentreonAuth::PWS_OCCULTATION
                ) {
                    $originalPassword = $this->findOriginalValueWithFieldIndex(
                        $configId,
                        "lua_parameter__value",
                        $matches[1],
                        $configGroupId
                    );
                    $output["lua_parameter__value_{$matches[1]}"] = $originalPassword;
                }
            }
        }
    }

    /**
     * Find a broker config original value based on group id
     *
     * @param int $configId
     * @param int $groupId
     * @param string $configKey
     *
     * @return string|null
     * @throws PDOException
     */
    private function findOriginalValueWithGroupId(int $configId, int $groupId, string $configKey): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT config_value FROM cfg_centreonbroker_info
            WHERE config_id = :configId
            AND config_key = :configKey
            AND config_group_id = :groupId'
        );
        $stmt->bindValue(':configId', $configId, PDO::PARAM_INT);
        $stmt->bindValue(':configKey', $configKey, PDO::PARAM_STR);
        $stmt->bindValue(':groupId', $groupId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row['config_value'] ?? null;
    }

    /**
     * Retrieve original db_password if it hasn't change
     *
     * @param int $configId
     * @param array<string,mixed> $values
     *
     * @throws PDOException
     */
    private function revealPasswords(int $configId, array &$values): void
    {
        if (isset($values['output'])) {
            foreach ($values['output'] as $key => &$output) {
                if (isset($output['db_password']) && $output['db_password'] === CentreonAuth::PWS_OCCULTATION) {
                    $originalPassword = $this->findOriginalValueWithGroupId($configId, $key, 'db_password');
                    $output['db_password'] = $originalPassword;
                }
            }
        }
    }

    /**
     * unset lua parameters with undefined index
     *
     * @param array $values
     * @param int $key
     * @return bool (false if no undefined index found)
     */
    private function removeUnindexedLuaParameters(array &$values, int $key): bool
    {
        if (array_key_exists('lua_parameter__value_#index#', $values['output'][$key])) {
            unset($values['output'][$key]['lua_parameter__value_#index#']);
            unset($values['output'][$key]['lua_parameter__name_#index#']);
            unset($values['output'][$key]['lua_parameter__type_#index#']);
            return true;
        }
        return false;
    }

    /**
     * unset lua parameters with value and name empty
     *
     * @param array &$values modified parameter
     * @param int $key
     */
    private function removeEmptyLuaParameters(array &$values, int $key): void
    {
        $paramKeys = array_keys($values['output'][$key]);
        $paramKeysIndexes = preg_filter('/lua_parameter__value_([0-9]*)/', '$1', $paramKeys ?? []);

        foreach ($paramKeysIndexes as $index) {
            if (
                !$values['output'][$key]['lua_parameter__name_' . $index]
                && !$values['output'][$key]['lua_parameter__value_' . $index]
            ) {
                unset($values['output'][$key]['lua_parameter__value_' . $index]);
                unset($values['output'][$key]['lua_parameter__name_' . $index]);
                unset($values['output'][$key]['lua_parameter__type_' . $index]);
            }
        }
    }

    /**
     * Get the list of forms for a config_id
     *
     * @param int $config_id The id of config
     * @param string $tag The tag name
     * @param int $page The page topology
     * @param Smarty $tpl The template Smarty
     * @return array
     * @throws HTML_QuickForm_Error
     */
    public function getForms($config_id, $tag, $page, $tpl)
    {
        $query = "SELECT config_key, config_value, config_group_id, grp_level, parent_grp_id, fieldIndex
            FROM cfg_centreonbroker_info WHERE config_id = %d
            AND config_group = '%s'
            AND subgrp_id IS NULL
            ORDER BY config_group_id";
        try {
            $res = $this->db->query(sprintf($query, $config_id, $tag));
        } catch (PDOException $e) {
            return [];
        }
        $formsInfos = [];
        $arrayMultipleValues = [];
        $isTypePassword = false;
        while ($row = $res->fetch()) {
            $fieldname = $tag . '[' . $row['config_group_id'] . '][' .
                $this->getConfigFieldName($config_id, $tag, $row) . ']';
            // Multi value for a multiselect
            if (isset($row['fieldIndex']) && !is_null($row['fieldIndex']) && $row['fieldIndex'] != "") {
                $fieldname = $tag . '[' . $row['config_group_id'] . '][' .
                    $this->getConfigFieldName($config_id, $tag, $row) . '_#index#]';
                $suffix = preg_match('/__(.+)$/', $row['config_key'], $matches) ? $matches[1] : '';
                $arrayMultipleValues[$fieldname]['suffix'] = $suffix;
                $arrayMultipleValues[$fieldname]['values'][$row['fieldIndex']] =
                    $isTypePassword && $suffix === 'value' ? CentreonAuth::PWS_OCCULTATION : $row['config_value'];
                if ($suffix === 'type' && $row['config_value'] === 'password') {
                    $isTypePassword = true;
                } elseif ($isTypePassword && $suffix === 'value') {
                    $isTypePassword = false;
                }
            } else {
                if (isset($formsInfos[$row['config_group_id']]['defaults'][$fieldname])) {
                    if (!is_array($formsInfos[$row['config_group_id']]['defaults'][$fieldname])) {
                        $formsInfos[$row['config_group_id']]['defaults'][$fieldname] = [$formsInfos[$row['config_group_id']]['defaults'][$fieldname]];
                    }
                    $formsInfos[$row['config_group_id']]['defaults'][$fieldname][] =
                        $row['config_key'] === 'db_password' ? CentreonAuth::PWS_OCCULTATION : $row['config_value'];
                } else {
                    $formsInfos[$row['config_group_id']]['defaults'][$fieldname] =
                        $row['config_key'] === 'db_password' ? CentreonAuth::PWS_OCCULTATION : $row['config_value'];
                    $formsInfos[$row['config_group_id']]['defaults'][$fieldname . '[' . $row['config_key'] . ']'] =
                        $row['config_key'] === 'db_password'
                        ? CentreonAuth::PWS_OCCULTATION
                        : $row['config_value']; // Radio button
                }
                if ($row['config_key'] == 'blockId') {
                    $formsInfos[$row['config_group_id']]['blockId'] = $row['config_value'];
                }
            }
        }
        $forms = [];
        $isMultiple = false;
        foreach (array_keys($formsInfos) as $key) {
            $qf = $this->quickFormById($formsInfos[$key]['blockId'], $page, $key, $config_id);
            //Replace loaded configuration with defaults external values
            [$tagId, $typeId] = explode('_', $formsInfos[$key]['blockId']);
            $tag = $this->getTagName($tagId);
            $fields = $this->getBlockInfos($typeId);

            foreach ($fields as $field) {
                $elementName = $this->getElementName($tag, $key, $field, $isMultiple);
                if (!is_null($field['value']) && $field['value'] != false) {
                    unset($formsInfos[$key]['defaults'][$elementName]); // = $this->getInfoDb($field['value']);
                }
                if (isset($arrayMultipleValues[$elementName])) {
                    if ($isMultiple && $field['group'] !== '') {
                        $parentGroup = $this->getParentGroups($field['group'], $isMultiple);
                        $parentGroup = $parentGroup . "_" . $key;
                        $radioButtonName = $elementName . '[' . $arrayMultipleValues[$elementName]['suffix'] . ']';
                        $arrayMultiple[$parentGroup][$elementName] = $arrayMultipleValues[$elementName]['values'];
                        $arrayMultiple[$parentGroup][$radioButtonName] = $arrayMultipleValues[$elementName]['values'];
                    }
                }
            }
            $qf->setDefaults($formsInfos[$key]['defaults']);
            $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
            $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
            $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
            $qf->accept($renderer);
            $forms[] = $renderer->toArray();
        }
        if (isset($arrayMultiple)) {
            foreach ($arrayMultiple as $key => $arrayMultipleS) {
                foreach ($arrayMultipleS as $key2 => $oneElemArray) {
                    foreach ($oneElemArray as $index => $oneElem) {
                        $this->arrayMultiple[$key][$index][$key2] = $oneElem;
                    }
                }
            }
        }

        $this->generateCdata();

        return $forms;
    }

    /**
     * Sort the fields by order display
     *
     * @param array $field1 The first field to sort
     * @param array $field2 The second field to sort
     * @return int
     */
    private function sortField($field1, $field2)
    {
        if ($field1['order'] == $field2['order']) {
            return 0;
        } elseif ($field1['order'] < $field2['order']) {
            return -1;
        } else {
            return 1;
        }
    }

    /**
     * Generate fieldtype array
     *
     * @param int $typeId The type id
     * @return array
     */
    public function getFieldtypes($typeId)
    {
        if (isset($this->fieldtypeCache[$typeId])) {
            return $this->fieldtypeCache[$typeId];
        }
        $fieldTypes = [];
        $block = $this->getBlockInfos($typeId);
        foreach ($block as $fieldInfos) {
            $fieldTypes[$fieldInfos['fieldname']] = $fieldInfos['fieldtype'];
        }
        $this->fieldtypeCache[$typeId] = $fieldTypes;
        return $this->fieldtypeCache[$typeId];
    }

    /**
     * Get helps message for forms
     *
     * @param int $config_id The configuration id
     * @param string $tag The tag of configuration
     * @return array The list of helps order by position in page
     */
    public function getHelps($config_id, $tag)
    {
        $this->nbSubGroup = 1;
        $query = "SELECT config_value, config_group_id
            FROM cfg_centreonbroker_info
            WHERE config_id = %d AND config_group = '%s'
            AND config_key = 'blockId'
            ORDER BY config_group_id";
        try {
            $res = $this->db->query(sprintf($query, $config_id, $tag));
        } catch (PDOException $e) {
            return [];
        }
        $helps = [];
        while ($row = $res->fetchRow()) {
            [$tagId, $typeId] = explode('_', $row['config_value']);
            $pos = $row['config_group_id'];
            $fields = $this->getBlockInfos((int) $typeId);
            $help = [];
            $help[] = ['name' => $tag . '[' . $pos . '][name]', 'desc' => _('The name of block configuration')];
            $help[] = ['name' => $tag . '[' . $pos . '][type]', 'desc' => _('The type of block configuration')];
            foreach ($fields as $field) {
                $fieldname = '';
                if ($field['group'] !== '') {
                    $fieldname .= $this->getParentGroups($field['group']);
                }
                $fieldname .= $field['fieldname'];
                $help[] = ['name' => $tag . '[' . $pos . '][' . $fieldname . ']', 'desc' => _($field['description'])];
            }
            $helps[] = $help;
            $pos++;
        }
        return $helps;
    }

    /**
     * Get the list of values for a select or radio
     *
     * @param int $fieldId The field ID
     * @return array
     */
    private function getListValues($fieldId)
    {
        if (isset($this->listValues[$fieldId])) {
            return $this->listValues[$fieldId];
        }
        $query = "SELECT v.value_name, v.value_value FROM cb_list_values v, cb_list l
                WHERE l.cb_list_id = v.cb_list_id AND l.cb_field_id = %d";
        try {
            $res = $this->db->query(sprintf($query, $fieldId));
        } catch (PDOException $e) {
            return [];
        }
        $ret = [];
        while ($row = $res->fetchRow()) {
            $ret[$row['value_value']] = $row['value_name'];
        }
        $this->listValues[$fieldId] = $ret;
        return $this->listValues[$fieldId];
    }

    /**
     * Get the default value for a list
     *
     * @param int $fieldId The field ID
     * @return string|null
     */
    public function getDefaults($fieldId)
    {
        if (isset($this->defaults[$fieldId])) {
            return $this->defaults[$fieldId];
        }
        $query = "SELECT cbl.default_value, cblv.value_value FROM cb_list_values cblv "
            . "LEFT JOIN cb_list cbl ON cblv.cb_list_id = cbl.cb_list_id "
            . "INNER JOIN cb_field cbf ON cbf.cb_field_id = cbl.cb_field_id "
            . "WHERE cbl.cb_field_id = %d "
            . "AND cbf.fieldtype != 'multiselect' ";
        try {
            $res = $this->db->query(sprintf($query, $fieldId));
        } catch (PDOException $e) {
            return null;
        }
        $row = $res->fetch();

        $this->defaults[$fieldId] = null;
        if (!is_null($row) && $row !== false) {
            if (!is_null($row['default_value']) && $row['default_value'] != '') {
                $this->defaults[$fieldId] = $row['default_value'];
            } elseif (!is_null($row['value_value']) && $row['value_value'] != '') {
                $this->defaults[$fieldId] = $row['value_value'];
            }
        } else {
            $externalDefaultValue = $this->getExternalDefaultValue($fieldId);
            if (!is_null($externalDefaultValue) && $externalDefaultValue != '') {
                $this->defaults[$fieldId] = $externalDefaultValue;
            }
        }

        return $this->defaults[$fieldId];
    }

    /**
     *
     * @param $fieldId
     *
     * @return bool|mixed|string|null
     * @throws PDOException
     */
    private function getExternalDefaultValue($fieldId)
    {
        $externalValue = null;
        $query = 'SELECT `external` FROM cb_field WHERE cb_field_id = ' . $fieldId;
        $res = $this->db->query($query);

        if (!$res) {
            $externalValue = null;
        }

        $row = $res->fetch();
        if (!is_null($row)) {
            $finalInfo = $this->getInfoDb($row['external']);
            if (!is_array($finalInfo)) {
                $externalValue = $finalInfo;
            }
        }

        return $externalValue;
    }

    /**
     * Get static information from database
     *
     * @param string $string The string for get information
     * @return array|bool|mixed|string
     * @throws Exception
     */
    public function getInfoDb($string)
    {
        global $pearDBO;

        if ($string === null) {
            return false;
        }

        $monitoringDb = $pearDBO ?? new CentreonDB('centstorage');

        // Default values
        $s_db = "centreon";
        $s_rpn = null;
        // Parse string
        $configs = explode(':', $string);
        foreach ($configs as $config) {
            if (!str_contains($config, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $config);
            switch ($key) {
                case 'D':
                    $s_db = $value;
                    break;
                case 'T':
                    $s_table = $value;
                    break;
                case 'C':
                    $s_column = $value;
                    break;
                case 'F':
                    $s_filter = $value;
                    break;
                case 'K':
                    $s_key = $value;
                    break;
                case 'CK':
                    $s_column_key = $value;
                    break;
                case 'RPN':
                    $s_rpn = $value;
                    break;
            }
        }
        // Construct query
        if (!isset($s_table) || !isset($s_column)) {
            return false;
        }
        $query = "SELECT `" . $s_column . "` FROM `" . $s_table . "`";
        if (isset($s_column_key) && isset($s_key)) {
            $query .= " WHERE `" . $s_column_key . "` = '" . $s_key . "'";
        }

        // Execute the query
        try {
            switch ($s_db) {
                case 'centreon':
                    $res = $this->db->query($query);
                    break;
                case 'centreon_storage':
                    $res = $monitoringDb->query($query);
                    break;
            }
        } catch (PDOException $e) {
            return false;
        }
        $infos = [];
        while ($row = $res->fetchRow()) {
            $val = $row[$s_column];
            if (!is_null($s_rpn)) {
                $val = $this->rpnCalc($s_rpn, $val);
            }
            $infos[] = $val;
        }
        if (count($infos) == 0) {
            return "";
        } elseif (count($infos) == 1) {
            return $infos[0];
        }
        return $infos;
    }

    /**
     * Apply a simple RPN operation
     *
     * The rpn operation begin by the value
     *
     * @param string $rpn The rpn operation
     * @param int $val The value for apply
     * @return mixed The value with rpn apply or the value is errors
     */
    private function rpnCalc($rpn, $val)
    {
        if (!is_numeric($val)) {
            return $val;
        }
        try {
            $val = array_reduce(
                preg_split('/\s+/', $val . ' ' . $rpn),
                [$this, 'rpnOperation']
            );
            return $val[0];
        } catch (InvalidArgumentException $e) {
            return $val;
        }
    }

    /**
     * Apply the operator
     *
     * @param array $result List of numerics
     * @param mixed $item Current item
     * @return array
     * @throws InvalidArgumentException
     */
    private function rpnOperation($result, $item)
    {
        if (in_array($item, ['+', '-', '*', '/'])) {
            if (count($result) < 2) {
                throw new InvalidArgumentException('Not enough arguments to apply operator');
            }
            $a = $result[0];
            $b = $result[1];
            $result = [];
            $result[0] = eval("return $a $item $b;");
        } elseif (is_numeric($item)) {
            $result[] = $item;
        } else {
            throw new InvalidArgumentException('Unrecognized symbol ' . $item);
        }
        return $result;
    }

    /**
     * Check event max queue size value
     *
     * if the value is too small, centreon broker will spend time
     * to write information directly to hard drive. So we prefer to
     * use more memory in order to avoid IO.
     *
     * @param int $value maximum number of event in the queue
     * @return int maximum number of event in the queue
     *
     */
    private function checkEventMaxQueueSizeValue($value)
    {
        if (!isset($value) || $value == "" || $value < 10000) {
            $value = 10000;
        }
        return $value;
    }

    /**
     * Get the element name for form
     *
     * @param string $tag The tag name
     * @param int $formId The form id
     * @param array $field The field information
     * @return string
     */
    private function getElementName($tag, $formId, $field, &$isMultiple = false)
    {
        $elementName = $tag . '[' . $formId . '][';
        if (!is_null($field['group']) && $field['group'] !== '') {
            $elementName .= $this->getParentGroups($field['group'], $isMultiple);
        }
        $elementName .= $field['fieldname'] . (($isMultiple) ? "_#index#" : "") . ']';
        return $elementName;
    }

    /**
     * Get the string for parent groups
     *
     * @param int $groupId The group id
     * @return string
     */
    public function getParentGroups($groupId, &$isMultiple = false, &$displayName = "")
    {
        $elemStr = '';
        try {
            $res = $this->db->query(
                sprintf(
                    "SELECT groupname, group_parent_id, multiple, displayname
                FROM cb_fieldgroup WHERE cb_fieldgroup_id = %d",
                    $groupId
                )
            );
        } catch (PDOException $e) {
            return '';
        }
        if ($row = $res->fetchRow()) {
            if ($row['group_parent_id'] !== '') {
                $elemStr .= $this->getParentGroups($row['group_parent_id'], $isMultiple, $displayName);
            }
            if ($row['multiple'] !== '' && $row['multiple'] == 1) {
                $isMultiple = true;
            }
            if (!$isMultiple) {
                $elemStr .= $row['groupname'] . '__' . $this->nbSubGroup++ . '__';
            } elseif ($elemStr != "") {
                $elemStr .= '__' . $row['groupname'] . '__';
            } else {
                $elemStr .= $row['groupname'] . '__';
            }
            if (!empty($row['displayname'])) {
                $displayName = $row['displayname'];
            }
        }
        return $elemStr;
    }

    /**
     * Get configuration fieldname for loading configuration from database
     *
     * @param int $configId The configuration ID
     * @param string $configGroup The configuration group (tag)
     * @param array $info The information
     * @return string
     */
    private function getConfigFieldName($configId, $configGroup, $info)
    {
        $elemStr = $info['config_key'];
        if ($info['grp_level'] != 0) {
            $error = false;
            try {
                $res = $this->db->query(sprintf(
                    "SELECT config_key, config_value, config_group_id, grp_level, parent_grp_id
               FROM cfg_centreonbroker_info
               WHERE config_id = %d
                   AND config_group = '%s'
           AND subgrp_id = %d
           AND grp_level = %d
           AND config_group_id = %d",
                    $configId,
                    $configGroup,
                    $info['parent_grp_id'],
                    $info['grp_level'] - 1,
                    $info['config_group_id']
                ));
            } catch (PDOException $e) {
                $error = true;
            }
            if ($error || $res->rowCount() == 0) {
                return $elemStr;
            }
            $row = $res->fetchRow();
            $elemStr = $this->getConfigFieldName(
                $configId,
                $configGroup,
                $row
            ) . '__' . $info['parent_grp_id'] . '__' . $elemStr;
        }
        return $elemStr;
    }

    /**
     * @param $sName
     *
     * @return int
     * @throws PDOException
     */
    public function isExist($sName)
    {
        $bExist = 0;
        if (empty($sName)) {
            return $bExist;
        }

        $statement = $this->db->prepare(
            <<<'SQL'
                SELECT COUNT(config_id) as nb FROm cfg_centreonbroker
                WHERE config_name = :configName
                SQL
        );
        $statement->bindValue(':configName', $this->db->escape($sName), PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch();
        if ($row['nb'] > 0) {
            $bExist = 1;
        }
        return $bExist;
    }

    /**
     * Replace a Broker config inputs and outputs configurations.
     *
     * @param int $configId
     * @param array<string|int|array> $values
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Throwable
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @throws \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @throws TransportException
     * @throws InvalidParameterException
     * @throws MissingMandatoryParametersException
     * @throws RouteNotFoundException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function updateCentreonBrokerInfosByAPI(int $configId, array $values): void
    {
        global $basePath;

        // exclude multiple parameters load with broker js hook
        $keepLuaParameters = false;
        if (isset($values['output'])) {
            foreach ($values['output'] as $key => $output) {
                if ($output['type'] === 'lua') {
                    if ($this->removeUnindexedLuaParameters($values, $key)) {
                        $keepLuaParameters = true;
                    }
                    $this->removeEmptyLuaParameters($values, $key);
                }
            }
        }

        $this->revealLuaPasswords($configId, $values);
        $this->revealPasswords($configId, $values);

        // Clean the informations for this id
         $kernel = Kernel::createForWeb();
        /** @var Logger $logger */
        $logger = $kernel->getContainer()->get(Logger::class);
        /** @var ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository */
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );
        /** @var FeatureFlags $featureFlagManager */
        $featureFlagManager = $kernel->getContainer()->get(Core\Common\Infrastructure\FeatureFlags::class);

        $vaultConfiguration = $readVaultConfigurationRepository->find();
        if ($featureFlagManager->isEnabled('vault_broker') && $vaultConfiguration !== null) {
            /** @var ReadVaultRepositoryInterface $readVaultRepository */
            $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
            /** @var WriteVaultRepositoryInterface $writeVaultRepository */
            $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::BROKER_VAULT_PATH);
            $this->retrievePasswordsFromVault($values, $readVaultRepository);
            deleteBrokerConfigsFromVault($writeVaultRepository, [$configId]);
        }

        $query = 'DELETE FROM cfg_centreonbroker_info WHERE config_id = '
            . $configId
            . ($keepLuaParameters ? ' AND config_key NOT LIKE "lua\_parameter\_%"' : '');
        $this->db->query($query);

        [$groups_infos, ] = $this->getGroupsInfos($values);

        /** @var Core\Infrastructure\Common\Api\Router $router */
        $router = $kernel->getContainer()->get(Core\Infrastructure\Common\Api\Router::class)
        ?? throw new LogicException('Router not found in container');
        $client = new Symfony\Component\HttpClient\CurlHttpClient();
        $headers = [
            'Content-Type' => 'application/json',
            'Cookie' => 'PHPSESSID=' . $_COOKIE['PHPSESSID'],
        ];
        $parameters = ['brokerId' => $configId];
        if ($basePath) {
            $parameters['base_uri'] = $basePath;
        }

        foreach($groups_infos as $tag => $groups) {
            $parameters['tag'] = $tag === 'input' ? 'inputs' : 'outputs';
            $url = $router->generate(
                'AddBrokerInputOutput',
                $parameters,
                Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL,
            );

            foreach($groups as $group) {
                $payload = $this->buildPayload($group);
                $response = $client->request(
                    'POST',
                    $url,
                    [
                        'headers' => $headers,
                        'body' => json_encode($payload),
                    ],
                );
                if ($response->getStatusCode() !== 201) {
                    $content = json_decode($response->getContent(false));
                    throw new Exception($content->message ?? 'Unexpected return status');
                }
            }
        }
    }

    /**
     * @param array<string|int|null|array<string|int|null|array<string|int|null>>> $inputOutput
     *
     * @return array
     */
    private function buildPayload(array $inputOutput): array
    {
        /** @var string $blockId */
        $blockId = $inputOutput['blockId'];
        [, $typeId] = explode('_', $blockId);
        $fieldTypes = $this->getFieldtypesWithGroup((int) $typeId);

        $payload = [
            'name' => $inputOutput['name'],
            'type' => (int) $typeId,
            'parameters' => [],
        ];

        foreach($inputOutput as $fieldName => $fieldValue) {
            if ($fieldName === 'multiple_fields') {
                foreach($fieldValue as $index => $groups) {
                    foreach($groups as $subName => $subValue) {
                        [$groupName, $name] = explode('__', $subName);

                        $fieldType = $fieldTypes[$groupName][$name];
                        $payload['parameters'][$groupName][$index] ??= [];
                        $this->addToPayload($payload['parameters'][$groupName][$index], $fieldType, $name, $subValue);
                    }
                }
            } else {
                if (is_array($fieldValue)) {
                    if (str_contains($fieldName, '__')) {
                        [, , $name] = explode('__', $fieldName);
                    } else {
                        $name = $fieldName;
                    }
                    $fieldType = $fieldTypes[$name] ?? null;
                } else {
                    $fieldType = $fieldTypes[$fieldName] ?? null;
                }
                if ($fieldType !== null) {
                    $this->addToPayload($payload['parameters'], $fieldType, $fieldName, $fieldValue);
                }
            }
        }

        foreach($fieldTypes as $name => $type) {
            if ($type == 'multiselect') {
                $name = "filters_{$name}";
            } elseif (is_array($type)) {
                $type = 'grouped';
            }
            if (! array_key_exists($name, $payload['parameters'])) {
                $payload['parameters'][$name] = match ($type) {
                    'select', 'text', 'password', 'int', 'radio' => null,
                    'multiselect', 'grouped' => [],
                };
            }
        }

        return $payload;
    }

    /**
     * Summary of addToPayload
     * @param array<mixed> $payload
     * @param string $fieldType
     * @param string $fieldName
     * @param string|array<mixed> $fieldValue
     *
     * @return void
     */
    private function addToPayload(
        array &$payload,
        string $fieldType,
        string $fieldName,
        string|array $fieldValue
    ): void {
        switch ($fieldType) {
            case 'select':
            case 'text':
                $payload[$fieldName] = $fieldValue === "" ? null : $fieldValue;
                break;
            case 'int':
                $payload[$fieldName] = $fieldValue === "" ? null : (int) $fieldValue;
                break;
            case 'radio':
                $payload[$fieldName] = $fieldValue[$fieldName];
                break;
            case 'multiselect':
                [$category, , $name] = explode('__', $fieldName);
                $payload["{$category}_{$name}"] = $fieldValue;
                break;
            case 'password':
                $payload[$fieldName] = $fieldValue;
                break;
            default:
                break;
        }
    }

    /**
     * @param array<mixed> $values
     *
     * @return array{array<mixed>,array<mixed>}
     */
    private function getGroupsInfos(array $values): array
    {
        $groups_infos = [];
        $groups_infos_multiple = [];
        foreach ($this->getTags() as $group) {
            // Resort array
            if (isset($values[$group])) {
                foreach ($values[$group] as $infos) {
                    if (!isset($groups_infos[$group])) {
                        $groups_infos[$group] = [];
                    }
                    $newArray = [];
                    foreach ($infos as $key => $info) {
                        $is_multiple = preg_match('/(.+?)_(\d+)$/', $key, $result);
                        if ($is_multiple) {
                            if (!isset($newArray[$result[2]])) {
                                $newArray[$result[2]] = [];
                            }
                            $newArray[$result[2]][$result[1]] = $info;

                            unset($infos[$key]);
                        }
                    }
                    if ($newArray !== []) {
                        $groups_infos_multiple[] = $newArray;
                        $infos['multiple_fields'] = $newArray;
                    }
                    $groups_infos[$group][] = $infos;
                }
            }
        }

        return [$groups_infos, $groups_infos_multiple];
    }

    private function retrievePasswordsFromVault(array &$values, ReadVaultRepositoryInterface $readVaultRepository): void
    {
        foreach ($values['output'] as &$output) {
            foreach ($output as &$value) {
                if (is_string($value) && $this->isAVaultPath($value)) {
                    $vaultValue = $readVaultRepository->findFromPath($value);
                    $parameterKey = end(explode("::", $value));
                    $value = $vaultValue[$parameterKey] ?? $value;
                }
            }
        }
    }


    /**
     * Generate fieldtype array.
     *
     * @param int $typeId The type id
     * @return array
     */
    public function getFieldtypesWithGroup($typeId)
    {
        $fieldTypes = [];
        $block = $this->getBlockInfos($typeId);
        foreach ($block as $fieldInfos) {
            if ($fieldInfos['group_name'] !== null) {
                $fieldTypes[$fieldInfos['group_name']][$fieldInfos['fieldname']] = $fieldInfos['fieldtype'];
            } else {
                $fieldTypes[$fieldInfos['fieldname']] = $fieldInfos['fieldtype'];
            }
        }
        return $fieldTypes;
    }

}
