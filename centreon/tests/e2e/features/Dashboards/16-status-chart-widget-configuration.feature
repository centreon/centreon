@REQ_MON-34407
Feature: Configuring Status chart widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a Status chart on a dashboard
  To manipulate the properties of the status grid Widget and test the outcome of each manipulation.

  @TEST_MON-37889
  Scenario: Creating and configuring a status chart widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Status chart"
    Then configuration properties for the status chart widget are displayed
    When the dashboard administrator user selects resources for the widget
    Then a donut chart representing the statuses of this list of resources are displayed in the widget preview
    When the user saves the Status chart widget
    Then the Status chart widget is added in the dashboard's layout

  @TEST_MON-37890
  Scenario: Editing the displayed unit of Status chart widget
    Given a dashboard that includes a configured Status chart widget
    When the dashboard administrator user selects a particular unit in the displayed unit list
    Then the unit of the resources already displayed should be updated

  @TEST_MON-37891
  Scenario: Deleting Status chart widget
    Given a dashboard featuring two Status chart widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  @TEST_MON-37892
  Scenario: Duplicating a Status chart widget
    Given a dashboard having a configured Status chart widget
    When the dashboard administrator user duplicates the Status chart widget
    Then a second Status Grid widget is displayed on the dashboard

  @TEST_MON-37893
  Scenario: Editing the displayed resource type of a Status chart widget
    Given a dashboard configuring Status chart widget
    When the dashboard administrator user updates the displayed resource type of the widget
    Then the widget is updated to reflect that change in displayed resource type