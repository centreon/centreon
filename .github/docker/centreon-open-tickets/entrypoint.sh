#!/bin/sh

su apache -s /bin/bash -c "/tmp/install-centreon-module.php -b /usr/share/centreon/bootstrap.php -m centreon-open-tickets"

mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon <<EOF
  INSERT INTO mod_open_tickets_rule(alias, provider_id, activate) VALUES ('glpi', 11, '1');
  INSERT INTO mod_open_tickets_form_clone (rule_id, \`order\`, uniq_id, label, value) VALUES
    (1, 0, 'groupList', 'Id', 'glpi_entity'),
    (1, 0, 'groupList', 'Label', 'Entity'),
    (1, 0, 'groupList', 'Type', '14'),
    (1, 0, 'groupList', 'Filter', ''),
    (1, 0, 'groupList', 'Mandatory', '1'),
    (1, 0, 'groupList', 'Sort', ''),
    (1, 1, 'groupList', 'Id', 'glpi_requester'),
    (1, 1, 'groupList', 'Label', 'Requester'),
    (1, 1, 'groupList', 'Type', '19'),
    (1, 1, 'groupList', 'Filter', ''),
    (1, 1, 'groupList', 'Mandatory', '1'),
    (1, 1, 'groupList', 'Sort', ''),
    (1, 0, 'bodyList', 'Name', 'Default'),
    (1, 0, 'bodyList', 'Value', '{\$user.alias} open ticket at {\$smarty.now|date_format:"%d/%m/%y %H:%M:%S"}

{\$custom_message}

{include file="file:\$centreon_open_tickets_path/providers/Abstract/templates/display_selected_lists.ihtml" separator=""}

{if \$host_selected|@count gt 0}
{foreach from=\$host_selected item=host}
Host: {\$host.name}
State: {\$host.state_str}
Duration: {\$host.last_hard_state_change_duration}
Output: {\$host.output|substr:0:1024}

{/foreach}
{/if}

{if \$service_selected|@count gt 0}
{foreach from=\$service_selected item=service}
Host: {\$service.host_name}
Service: {\$service.description}
State: {\$service.state_str}
Duration: {\$service.last_hard_state_change_duration}
Output: {\$service.output|substr:0:1024}
{/foreach}
{/if}'),
    (1, 0, 'bodyList', 'Default', '1'),
    (1, 0, 'mappingTicket', 'Value', '8'),
    (1, 0, 'mappingTicket', 'Id', 'Issue {include file="file:\$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
    (1, 1, 'mappingTicket', 'Arg', '1'),
    (1, 1, 'mappingTicket', 'Value', '{\$body}'),
    (1, 2, 'mappingTicket', 'Arg', '2'),
    (1, 2, 'mappingTicket', 'Value', '{\$select.glpi_entity.id}'),
    (1, 3, 'mappingTicket', 'Arg', '5'),
    (1, 3, 'mappingTicket', 'Value', '{\$select.glpi_itil_category.id}'),
    (1, 4, 'mappingTicket', 'Arg', '13'),
    (1, 4, 'mappingTicket', 'Value', '{\$select.glpi_requester.id}'),
    (1, 5, 'mappingTicket', 'Arg', '6'),
    (1, 5, 'mappingTicket', 'Value', '{\$select.glpi_users.id}'),
    (1, 6, 'mappingTicket', 'Arg', '12'),
    (1, 6, 'mappingTicket', 'Value', '{\$select.user_role.value}'),
    (1, 7, 'mappingTicket', 'Arg', '7'),
    (1, 7, 'mappingTicket', 'Value', '{\$select.glpi_group.id}'),
    (1, 8, 'mappingTicket', 'Arg', '11'),
    (1, 8, 'mappingTicket', 'Value', '{\$select.group_role.value}'),
    (1, 9, 'mappingTicket', 'Arg', '3'),
    (1, 9, 'mappingTicket', 'Value', '{\$select.urgency.value}'),
    (1, 10, 'mappingTicket', 'Arg', '4'),
    (1, 10, 'mappingTicket', 'Value', '{\$select.priority.value}'),
    (1, 11, 'mappingTicket', 'Arg', '9'),
    (1, 11, 'mappingTicket', 'Value', '{\$select.urgency.value}'),
    (1, 12, 'mappingTicket', 'Arg', '10'),
    (1, 12, 'mappingTicket', 'Value', '{\$select.glpi_supplier.id}');
  INSERT INTO mod_open_tickets_form_value (rule_id, uniq_id, value) VALUES
    (1, 'proxy_address', ''),
    (1, 'proxy_port', ''),
    (1, 'proxy_username', ''),
    (1, 'proxy_password', ''),
    (1, 'address', 'glpi'),
    (1, 'api_path', '/apirest.php'),
    (1, 'protocol', 'http'),
    (1, 'user_token', 'R0MIlEL91Hhh2OJIGIS9y43TDFWCRX0r2aClU7sI'),
    (1, 'app_token', 'Ns4CuByx9MBIZhkO83mMaKYrceFJ21YNmGDw59K8');
EOF
