export const INTERCEPTORS = {
  api: {
    localLogin: '/centreon/authentication/providers/configurations/local',
    navigation_list:
      '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  },
  openTickets: {
    // Entry point of the rule form actions (get-form-config, save-form-config)
    ruleAction:
      '/centreon/modules/centreon-open-tickets/views/rules/ajax/call.php',
    // Entry point of the Open Tickets widget actions (service-ack, schedule-check, close-ticket)
    widgetAction:
      '/centreon/widgets/open-tickets/src/ajax/callforwardmodule.php'
  }
};
