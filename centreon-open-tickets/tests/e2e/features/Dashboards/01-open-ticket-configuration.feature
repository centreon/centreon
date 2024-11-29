Feature: Configuring ticket managemetn in resrouce table widget
  As a Centreon user with dashboard update rights,
  I need to configure a resource table widget on a dashboard,
  To manipulate the ticket management properties and test the outcome of each manipulation.

  Scenario: Creating and Associating a Resource with a Ticket in a Dashboard's Resource Table Widget"
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator selects the widget type "resource table"
    Then configuration properties for ticket management are displayed
    When the dashboard administrator selects a resource to associate with a ticket
    Then the open ticket modal should appear
    When the dashboard administrator fills out the ticket creation form and submits the form
    Then a new ticket is created and the selected resource is associated with the ticket

  # Scenario: Creating, Associating, and Managing a Ticket with a Resource in a Dashboard's Resource Table Widget
  #   Given a dashboard in the dashboard administrator user's dashboard library
  #   When the dashboard administrator user selects the option to add a new widget
  #   And the dashboard administrator selects the widget type "resource table"
  #   Then configuration properties for ticket management are displayed
  #   When the dashboard administrator selects a resource to associate with a ticket
  #   Then the open ticket modal should appear
  #   When the dashboard administrator fills out the ticket creation form and submits the form
  #   Then a new ticket is created and the selected resource is associated with the ticket
  #   When the dashboard administrator clicks on the filter to view resources associated with a ticket
  #   Then the resources that are linked to a ticket should be displayed
  #   When the dashboard administrator clicks on the delete button of a ticket
  #   Then the ticket should be deleted and the resource should no longer be associated with the ticket