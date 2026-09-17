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
<header class="nav-top">
    <span class="hamburger material-icons" id="ham">menu</span>
    <div class="logo"></div>
</header>
<nav class="nav-drill">
    <ul class="nav-items nav-level-1">
        <?php foreach ($treeMenu as $index => $subMenu) { ?>
        <li class="nav-item <?php if (! empty($subMenu['children'])) { ?>nav-expand<?php } ?>">
            <a class="nav-link <?php if (! empty($subMenu['children'])) { ?>nav-expand-link<?php } ?>" href="#">
                <?= $subMenu['label']; ?>
            </a>
            <?php if (! empty($subMenu['children'])) { ?>
                <ul class="nav-items nav-expand-content">
                <?php foreach ($subMenu['children'] as $index2 => $subMenu2) { ?>
                    <?php if (! empty($subMenu2['children'])) { ?>
                        <li class="nav-item nav-expand">
                            <a class="nav-link nav-expand-link" href="#">
                                <?= $subMenu2['label']; ?>
                            </a>
                            <ul class="nav-items nav-expand-content">
                            <?php foreach ($subMenu2['children'] as $childrens) { ?>
                                <?php foreach ($childrens as $index3 => $subMenu3) { ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="main.php?p=<?= substr($index3, 1) . $subMenu3['options']; ?>">
                                            <?= $subMenu3['label']; ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            <?php } ?>
                            </ul>
                        </li>
                    <?php } else { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="main.php?p=<?= substr($index2, 1); ?>">
                                <?= $subMenu2['label']; ?>
                            </a>
                        </li>
                    <?php } ?>
                <?php } ?>
                </ul>
            <?php } ?>
        </li>
        <?php } ?>
        <li class="nav-item">
            <a class="nav-link" href="index.php?disconnect=1">
                <?= gettext('Logout'); ?>
            </a>
        </li>
    </ul>
</nav>
