@REQ_MON-34530
Feature: Configuring group monitoring widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a group monitoring on a dashboard
  To manipulate the properties of the status grid Widget and test the outcome of each manipulation.

  @TEST_MON-37889
  Scenario: Creating and configuring a group monitoring Grid widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Group monitoring"
    Then configuration properties for the Group monitoring widget are displayed
    When the dashboard administrator user selects resources for the widget
    Then a table representing the statuses of this list of resources are displayed in the widget preview
    When the user saves the Group monitoring widget
    Then the Group monitoring widget is added in the dashboard's layout

  @TEST_MON-37890
  Scenario: Editing the displayed resource status of group monitoring widget
    Given a dashboard that includes a configured Group monitoring widget
    When the dashboard administrator user selects a particular status in the displayed resource status list
    Then only the resources with this particular status are displayed in the Group monitoring Widget

  @TEST_MON-37891
  Scenario: Deleting group monitoring widget
    Given a dashboard featuring two group monitoring widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  @TEST_MON-37892
  Scenario: Duplicating a Group monitoring widget
    Given a dashboard having a configured group monitoring widget
    When the dashboard administrator user duplicates the group monitoring widget
    Then a second Status Grid widget is displayed on the dashboard
    And the second widget has the same properties as the first widget

  @TEST_MON-37893
  Scenario: Editing the displayed resource type of a group monitoring widget
    Given a dashboard configuring group monitoring widget
    When the dashboard administrator user updates the displayed resource type of the widget
    Then the widget is updated to reflect that change in displayed resource type

  @TEST_MON-130765
  Scenario: Access the resource status page by clicking on a resource from the group monitoring widget
    Given a dashboard with a group monitoring widget
    When the dashboard administrator clicks on a random resource
    Then the user should be redirected to the resource status screen and all the resources must be displayed
