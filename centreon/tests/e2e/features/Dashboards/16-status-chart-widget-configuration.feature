@REQ_MON-34407
Feature: Configuring Status Chart widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a Status Chart on a dashboard
  To manipulate the properties of the status Chart Widget and test the outcome of each manipulation.

  @TEST_MON-47883
  Scenario: Creating and configuring a status Chart widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Status Chart"
    Then configuration properties for the status chart widget are displayed
    When the dashboard administrator user selects resources for the widget
    Then a donut chart representing the statuses of this list of resources are displayed in the widget preview
    When the user saves the Status Chart widget
    Then the Status Chart widget is added in the dashboard's layout

  @TEST_MON-47886
  Scenario: Editing the displayed unit of Status Chart widget
    Given a dashboard that includes a configured Status Chart widget
    When the dashboard administrator user selects a particular unit in the displayed unit list
    Then the unit of the resources already displayed should be updated

  @TEST_MON-47887
  Scenario: Deleting a Status Chart widget
    Given a dashboard featuring two Status Chart widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  @TEST_MON-47888
  Scenario: Duplicating a Status Chart widget
    Given a dashboard having a configured Status Chart widget
    When the dashboard administrator user duplicates the Status Chart widget
    Then a second Status Chart widget is displayed on the dashboard

  @TEST_MON-47889
  Scenario: Editing the displayed resource type of a Status Chart widget
    Given a dashboard administrator user configuring a Status Chart widget
    When the dashboard administrator user updates the displayed resource type of the widget
    Then the widget is updated to reflect that change of displayed resource type

  @TEST_MON-130764
  Scenario: Access the resource status page by clicking on a resource from the status chart widget
    Given a dashboard with a Status Chart widget
    When the dashboard administrator clicks on a random resource
    Then the user should be redirected to the resource status screen and all the resources must be displayed