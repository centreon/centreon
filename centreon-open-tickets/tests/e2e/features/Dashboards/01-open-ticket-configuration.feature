@REQ_MON-34256
Feature: Configuring web page widget
  As a Centreon User with dashboard update rights,
  I need to configure a web page widget on a dashboard
  To manipulate the properties of the web page Widget and test the outcome of each manipulation.

  @TEST_MON-35084
  Scenario: Creating and configuring a new resource table widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator selects the widget type "resource table"
    Then configuration properties for ticket management are displayed
    When the dashboard administrator selects a resource to associate with a ticket
    Then the open ticket modal should appear
    When the dashboard administrator fills out the ticket creation form and submits the form
    Then a new ticket is created and the selected resource is associated with the ticket