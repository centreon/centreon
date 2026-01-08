#!/bin/sh
# Import users, acls, permissions and relations datasets for tests

# Import ACLs groups, menus, actions
centreon -u admin -p 'Centreon!2021' -i ./imports/acls/aclgroup.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/acls/aclmenu.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/acls/aclaction.csv

# Import ACLs permissions
centreon -u admin -p 'Centreon!2021' -i ./imports/permissions/aclactionperms.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/permissions/aclmenuperms.csv
# Import ACLs relations
centreon -u admin -p 'Centreon!2021' -i ./imports/relations/aclgroup_aclaction.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/relations/aclgroup_aclmenu.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/relations/aclgroup_allresources.csv

# Import users
centreon -u admin -p 'Centreon!2021' -i ./imports/users/administrator.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/users/editor.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/users/operator.csv
centreon -u admin -p 'Centreon!2021' -i ./imports/users/unprivileged.csv
