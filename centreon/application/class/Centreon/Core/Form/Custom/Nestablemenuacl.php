<?php
/*
 * Copyright 2005-2014 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */
namespace Centreon\Core\Form\Custom;

class Nestablemenuacl extends Customobject
{
    private static $aclmenudata = array();

    /**
     * Get checkbox html
     * 
     * @param int $menuId
     * @param string $uri
     * @return string
     */
    private static function getCheckboxHtml($menuId, $uri)
    {
        $routesData = \Centreon\Core\Di::getDefault()
            ->get('router')
            ->getRoutes();
        $str = "<span class=\"nestable_cb\">";
        $cbArray = array();
        foreach ($routesData as $data) {
            if (strstr($data['route'], $uri) && $data['acl']) {
                $cbArray[$data['acl']] = true;
            }
        }
        $acldata = 0;
        if (isset(self::$aclmenudata[$menuId])) {
            $acldata = self::$aclmenudata[$menuId];
        }
        $tmp = "<span class=\"acl_cb\">%s <input type=\"checkbox\" name=\"acl_%s[{$menuId}]\" %s></input></span>";
        if (isset($cbArray[\Centreon\Core\Acl::ADD])) {
            $checked = \Centreon\Core\Acl::isFlagSet($acldata, \Centreon\Core\Acl::ADD) ? 'checked' : '';
            $str .= sprintf($tmp, _('Create'), 'create', $checked);
        }
        if (isset($cbArray[\Centreon\Core\Acl::DELETE])) {
            $checked = \Centreon\Core\Acl::isFlagSet($acldata, \Centreon\Core\Acl::DELETE) ? 'checked' : '';
            $str .= sprintf($tmp, _('Delete'), 'delete', $checked);
        }
        if (isset($cbArray[\Centreon\Core\Acl::UPDATE])) {
            $checked = \Centreon\Core\Acl::isFlagSet($acldata, \Centreon\Core\Acl::UPDATE) ? 'checked' : '';
            $str .= sprintf($tmp, _('Update'), 'update', $checked);
        }
        if (isset($cbArray[\Centreon\Core\Acl::VIEW])) {
            $checked = \Centreon\Core\Acl::isFlagSet($acldata, \Centreon\Core\Acl::VIEW) ? 'checked' : '';
            $str .= sprintf($tmp, _('View'), 'view', $checked);
        }
        if (isset($cbArray[\Centreon\Core\Acl::ADVANCED])) {
            $checked = \Centreon\Core\Acl::isFlagSet($acldata, \Centreon\Core\Acl::ADVANCED) ? 'checked' : '';
            $str .= sprintf($tmp, _('Advanced'), 'advanced', $checked);
        }
        $str .= "</span>";
        return $str;
    }

    /**
     * Get menu html string
     *
     * @param array $menus
     * @param int $menuId
     * @return string
     */
    private static function getMenuString($menus, $menuId)
    {
        $str = "";
        foreach ($menus as $menu) {
            if ($menu['menu_id'] == $menuId) {
                $str .= "<li class=\"dd-item\" data-id=\"{$menuId}\">";
                $style = "";
                if ($menu['bgcolor']) {
                    $style = "style=\"background: {$menu['bgcolor']}\"";
                }
                $str .= "<div class=\"dd-handle\" {$style}>";
                if ($menu['icon_class']) {
                    $str .= "<i class=\"{$menu['icon_class']}\"></i> ";
                }
                $str .= $menu['name'];
                if (!count($menu['children'])) {
                    $str .= self::getCheckboxHtml($menuId, $menu['url']);
                }
                $str .= "</div>";
                if (count($menu['children'])) {
                    $str .= "<ol class=\"dd-list\">";
                    foreach ($menu['children'] as $cmenu) {
                        $str .= self::getMenuString($menu['children'], $cmenu['menu_id']);
                    }
                    $str .= "</ol>";
                }
                $str .= "</li>";
            }
        }
        return $str;
    }

    /**
     * Render html input
     *
     * @param array $element
     * @return array
     */
    public static function renderHtmlInput(array $element)
    {
        $tpl = \Centreon\Core\Di::getDefault()->get('template');
        $tpl->addCss('nestable.css');
        $tpl->addJs('jquery.nestable.js');
        $menus = \Centreon\Core\Di::getDefault()->get('menu')->getMenu();
        $menuStr = "";
        if (isset($element['label_extra']) && isset($element['label_extra']['id'])) {
            self::$aclmenudata = \Centreon\Repository\AclmenuRepository::getAclLevelByAclMenuId(
                $element['label_extra']['id']
            );
        }
        foreach ($menus as $menu) {
            $menuStr .= self::getMenuString($menus, $menu['menu_id']);
        }
        $myHtml = '
            <div class="dd">
            <ol class="dd-list dd-nodrag">
            '.$menuStr.'
            </ol>
            </div>
            ';
        $myJs = '
            $(".dd").nestable({
                handleClass: "dd-nohandle"
            });
        ';
        return array('html' => $myHtml, 'js' => $myJs);
    }
}
