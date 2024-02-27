@REQ_MON-24518
Feature: Configuring dashboard with all widgets
  As a Centreon User with dashboard update rights,
  I must be able to comprehensively configure all widgets within a dashboard
  This includes manipulating the properties of each widget and thoroughly testing the outcomes of these manipulations

  @TEST_MON-24937
  Scenario: Adding all widgets into the same dashboard
    Given the dashboard administrator redirected to dashboard interface
    When the dashboard administrator add generic text widget
    And the dashboard administrator add single metric widget
    And the dashboard administrator metrics graph widget
    And the dashboard administrator add top bottom widget
    And the dashboard administrator add Status grid widget
    Then the dashboard administrator save the dashboard

  @TEST_MON-24937
  Scenario: Editing widgets positions
    Given the dashboard administrator redirected to dashboard screen
    When the dashboard administrator update widgets positions and save updates
    Then the new widget positions must be saved

  Scenario: Accessing to ressource status page while clicking on <widgetType> widget
    Given the dashboard administrator is now on the dashboard interface
    When the dashboard administrator clicks on view resource status button from '<widgetType>' widget
    Then the dashboard administrator should be redirected to '<widgetType>' widget resources

    Examples:
      | widgetType      |
      | single metric   |
      | metrics graph   |
      | top button      |
      | status grid     |