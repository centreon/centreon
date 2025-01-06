Feature: Configuring ticket management  in resrouce table widget
  As a Centreon user with dashboard update rights,
  I need to configure a resource table widget on a dashboard,
  To manipulate the ticket management properties and test the outcome of each manipulation.

  @TEST_MON-154358
  Scenario: Creating and Associating a Resource with a Ticket in a Dashboard's Resource Table Widget"
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator selects the widget type "resource table"
    Then configuration properties for ticket management are displayed
    When the dashboard administrator selects a resource to associate with a ticket
    Then the open ticket modal should appear
    When the dashboard administrator fills out the ticket creation form and submits the form
    Then a new ticket is created and the selected resource is associated with the ticket

  @TEST_MON-155206
  Scenario: Removing a Ticket from a Resource in the Resource Table Widget
    Given the dashboard administrator accesses the resource table widget
    When the dashboard administrator clicks on the delete button of a ticket
    Then the ticket should be deleted and the resource should no longer be associated with the ticket
