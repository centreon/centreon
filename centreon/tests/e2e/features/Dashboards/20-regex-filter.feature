Feature: Filtering resources using regex in dashboard widgets
    As a Centreon user with rights to update dashboards,
    I want to configure widgets using regex-based filters
    so that I can display only the relevant hosts or services in my dashboard.

  @TEST_MON-178797
  Scenario: Create and configure a "Group monitoring" widget with regex-based host group selection
    Given a dashboard exists in the dashboard administrator's library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Group monitoring"
    Then configuration options for the "Group monitoring" widget are displayed
    And the Save button is disabled
    When the dashboard administrator user applies a regex filter to select host group resources for the widget
    Then a table showing statuses of the matching resources are displayed in the widget preview
    And the Save button is enabled
    When the user saves the "Group monitoring" widget
    Then the Group monitoring widget is added in the dashboard's layout

  @TEST_MON-178798
  Scenario: Creating and configuring a "Resource table" widget with regex-based host filtering
    Given a dashboard exists in the dashboard administrator's library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Resource table"
    Then configuration options for the "Resource table" widget are displayed
    When the dashboard administrator user applies a regex filter to select the hosts and services displayed by the widget
    And the user saves the "Resource table" widget
    Then the resource table widget is added to the dashboard layout and correctly displays the filtered hosts

  @TEST_MON-178799
  Scenario: Create and configure a "Resource Table" widget with regex-based service filtering
    Given a dashboard exists in the dashboard administrator's library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Resource table"
    Then configuration options for the "Resource table" widget are displayed
    When the dashboard administrator selects a service using a regex filter to configure the widget’s data source
    And the user saves the "Resource table" widget
    Then the resource table widget is added to the dashboard layout and shows the filtered service correctly
