UPDATE `topology`
SET
    `topology_url` = '/administration/about',
    `readonly` = '1',
    `is_react` = '1',
    `topology_parent` = 5,
    `topology_order` = 15,
    `topology_group` = 1
WHERE `topology_name` = 'About' AND `topology_page` = 506;