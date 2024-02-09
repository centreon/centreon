@REQ_MON-24518
Feature: Configuring resource table widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a resource table on a dashboard
  To manipulate the properties of the resource table Widget and test the outcome of each manipulation.

  @TEST_MON-35094
  Scenario: Editing the displayed resource status of resource table widget
    Given a dashboard containing a configured resource table widget
    When the dashboard administrator user selects a particular status in the displayed resource status list
    Then only the resources with this particular status are displayed in the resource table Widget
    When the dashboard administrator user selects all the status and save changes
    Then all the resources having the status selected are displayed in the resource table Widget

  @TEST_MON-35094
  Scenario: Editing the display type of resource table widget
    Given a dashboard that includes a configured resource table widget
    When the dashboard administrator user selects view by host as a display type
    Then only the hosts must be displayed
    When the dashboard administrator user selects view by service as a display type
    Then only the services must be displayed

  @TEST_MON-35096
  Scenario: Displaying unhandled ressources on a resource table widget
    Given a dashboard featuring a configured resource table widget
    When the dashboard administrator user select all the status of the dataset selection
    Then only the unhandled ressources are displayed in the ressrouce table widget

  Scenario: Deleting a resource table widget
    Given a dashboard featuring two resource table widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

    @TEST_MON-35098
  Scenario: Duplicating resource table widget
    Given a dashboard having a configured ressrouce table widget
    When the dashboard administrator user duplicates the resource table widget
    Then a second ressrouce table widget is displayed on the dashboard having the same properties as the first widget

  @TEST_MON-35084
  Scenario: Creating and configuring a new resource table widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And selects the widget type "resource table"
    Then configuration properties for the resource table widget are displayed
    When the dashboard administrator user selects a resource and a metric for the widget to report on
    When the user saves the resource table widget
    Then the resource table widget is added to the dashboard's layout
