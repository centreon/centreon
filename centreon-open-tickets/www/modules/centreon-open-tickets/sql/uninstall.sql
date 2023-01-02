
DROP TABLE `mod_open_tickets_form_clone`;
DROP TABLE `mod_open_tickets_form_value`;
DROP TABLE `mod_open_tickets_rule`;
DROP TABLE `@DB_CENTSTORAGE@`.`mod_open_tickets_link`;
DROP TABLE `@DB_CENTSTORAGE@`.`mod_open_tickets_data`;
DROP TABLE `@DB_CENTSTORAGE@`.`mod_open_tickets`;

DELETE FROM topology WHERE topology_page = '60420' AND topology_name = 'Rules';
DELETE FROM topology WHERE topology_parent = '604' AND topology_name = 'Open Tickets';
DELETE FROM topology_JS WHERE id_page = '60420';

DELETE FROM topology WHERE topology_page = '20320' AND topology_name = 'Ticket Logs';
DELETE FROM topology_JS WHERE id_page = '20320';

DELETE FROM widget_parameters_field_type WHERE ft_typename = 'openTicketsRule';
