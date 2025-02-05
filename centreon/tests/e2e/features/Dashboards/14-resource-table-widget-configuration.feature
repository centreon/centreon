@REQ_MON-34256
Feature: Configuring resource table widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a resource table on a dashboard
  To manipulate the properties of the resource table Widget and test the outcome of each manipulation.

  @TEST_MON-35094
  Scenario: Editing the display type of resource table widget
    Given a dashboard that includes a configured resource table widget
    When the dashboard administrator user selects view by host as a display type
    Then only the hosts must be displayed
    When the dashboard administrator user selects view by service as a display type
    Then only the services must be displayed

  @TEST_MON-35094
  Scenario: Editing the displayed resource status of resource table widget
    Given a dashboard containing a configured resource table widget
    When the dashboard administrator user selects a particular status in the displayed resource status list
    Then only the resources with this particular status are displayed in the resource table Widget
    When the dashboard administrator user selects all the status and save changes
    Then all the resources having the status selected are displayed in the resource table Widget

  @TEST_MON-35096
  Scenario: Displaying unhandled resources on a resource table widget
    Given a dashboard containing a configured resource table widget
    When the dashboard administrator user selects all the status and save changes
    Then only the unhandled resources are displayed in the resource table widget

  # @TEST_MON-35096
  # Scenario: Deleting a resource table widget
  #   Given a dashboard featuring two resource table widgets
  #   When the dashboard administrator user deletes one of the widgets
  #   Then only the contents of the other widget are displayed

  # @TEST_MON-35098
  # Scenario: Duplicating resource table widget
  #   Given a dashboard having a configured resource table widget
  #   When the dashboard administrator user duplicates the resource table widget
  #   Then a second resource table widget is displayed on the dashboard having the same properties as the first widget

  @TEST_MON-35084
  Scenario: Creating and configuring a new resource table widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator selects the widget type "resource table"
    Then configuration properties for the resource table widget are displayed
    When the dashboard administrator user selects a resource and a metric for the widget to report on
    When the user saves the resource table widget
    Then the resource table widget is added to the dashboard's layout

  @TEST_MON-130766
  Scenario: Access the resource status page by clicking on a resource from the ressource table widget
    Given a dashboard with a resource table widget
    When the dashboard administrator clicks on a random resource
    Then the user should be redirected to the resource status screen and all the resources must be displayed

  @TEST_MON-146675
  Scenario: Set a resource status to downtime
    Given a dashboard containing a resource table widget
    When the dashboard administrator clicks on a random resource from the resource table
    And the dashboard administrator clicks on the downtime button and submits
    And the dashboard administrator clicks on the downtime filter
    Then the resources set to in downtime should be displayed

  @TEST_MON-146676
  Scenario: Set a resource status to acknowledged
    Given a dashboard containing a resource table widget
    When the dashboard administrator clicks on a random resource from the resource table
    And the dashboard administrator clicks on the acknowledge button and submits
    And the dashboard administrator clicks on the acknowledge filter
    Then the resources set to acknowledged should be displayed