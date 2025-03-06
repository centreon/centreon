#!/bin/sh

su apache -s /bin/bash -c "/tmp/install-centreon-module.php -b /usr/share/centreon/bootstrap.php -m centreon-open-tickets"

if [ ! -z ${GLPI_HOST} ] && getent hosts ${GLPI_HOST}; then
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
        {/if}'
      ),
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
      (1, 'address', '${GLPI_HOST}'),
      (1, 'api_path', '/apirest.php'),
      (1, 'protocol', 'http'),
      (1, 'user_token', '${GLPI_USER_TOKEN}'),
      (1, 'app_token', '${GLPI_APP_TOKEN}'),
      (1, 'message_confirm', '<table class="table">
        <tr>
            <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{\$title}</h3></td>
        </tr>
        {if \$ticket_is_ok == 1}
            <tr><td class="FormRowField" style="padding-left:15px;">New ticket opened: {\$ticket_id}.</td></tr>
        {else}
            <tr><td class="FormRowField" style="padding-left:15px;">Error to open the ticket: {\$ticket_error_message}.</td></tr>
        {/if}
        </table>'
      ),
      (1, 'macro_ticket_id', 'TICKET_ID'),
      (1, 'confirm_autoclose', ''),
      (1, 'ack', 'yes'),
      (1, 'schedule_check', ''),
      (1, 'attach_files', ''),
      (1, 'close_ticket_enable', ''),
      (1, 'error_close_centreon', ''),
      (1, 'url', '{\$protocol}://{\$address}/front/ticket.form.php?id={\$ticket_id}'),
      (1, 'format_popup', '<table class="table">
        <tr>
            <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{\$title}</h3></td>
        </tr>
        <tr>
            <td class="FormRowField" style="padding-left:15px;">{\$custom_message.label}</td>
            <td class="FormRowValue" style="padding-left:15px;">
                <textarea id="custom_message" name="custom_message" cols="50" rows="6"></textarea>
            </td>
        </tr>
        {include file="file:\$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
        <!--<tr>
            <td class="FormRowField" style="padding-left:15px;">Add graphs</td>
            <td class="FormRowValue" style="padding-left:15px;"><input type="checkbox" name="add_graph" value="1" /></td>
        </tr>-->
        </table>'
      );
EOF
fi
