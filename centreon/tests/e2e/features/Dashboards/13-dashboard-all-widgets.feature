Feature: Configuring dashboard with all widgets
  As a Centreon User with dashboard update rights,
  I must be able to comprehensively configure all widgets within a dashboard
  This includes manipulating the properties of each widget and thoroughly testing the outcomes of these manipulations

  @TEST_MON-37042
  Scenario: Adding all widgets into the same dashboard
    Given a dashboard administrator on the dashboard web interface
    When the dashboard administrator adds a Generic text widget
    And the dashboard administrator adds a Single metric widget
    And the dashboard administrator adds a Metrics graph widget
    And the dashboard administrator adds a Top bottom widget
    And the dashboard administrator adds a Status grid widget and saves changes
    Then the dashboard administrator is now on the newly created dashboard in view mode

  @TEST_MON-37043
  Scenario: Editing the layout of a multi-widget dashboard
    Given a dashboard administrator who has just configured a multi-widget dashboard
    When the dashboard administrator updates the positions of the widgets and saves the dashboard
    Then the dashboard is updated with the new widget layout

  @TEST_MON-37044
  Scenario: Accessing the resource status page when clicking on the <widgetType> widget
    Given the dashboard administrator with a configured multi-widget dashboard
    When the dashboard administrator clicks on the "view Resource Status" button from the '<widgetType>' widget
    Then the dashboard administrator should be redirected to the '<widgetType>' widget resources

    Examples:
      | widgetType      |
      | single metric   |
      | metrics graph   |
      | top bottom      |
      | status grid     |