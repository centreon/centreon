DROP TABLE `mod_open_tickets_rule`;
DROP TABLE `mod_open_tickets_form_clone`;
DROP TABLE `mod_open_tickets_form_value`;

DELETE FROM topology WHERE topology_page = '60420' AND topology_name = 'Rules';
DELETE FROM topology WHERE topology_parent = '604' AND topology_name = 'Open Tickets';
DELETE FROM topology_JS WHERE id_page = '60420';