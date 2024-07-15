@REQ_MON-24138
Feature: Configuring a Single Metric Widget
  As a Centreon User with dashboard update rights,
  I want to set up a widget displaying a single metric on a dashboard
  To manipulate the properties of the Single Metric Widget and test the outcome of each manipulation.

  @TEST_MON-23784
  Scenario: Creating and configuring a new Single Metric widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And selects the widget type "Single metric"
    Then configuration properties for the Single metric widget are displayed
    When the dashboard administrator user selects a resource and the metric for the widget to report on
    Then information about this metric is displayed in the widget preview
    When the user saves the Single metric widget
    Then the Single metric widget is added in the dashboard's layout
    And the information about the selected metric is displayed

  @TEST_MON-23786 @ignore
  Scenario: Duplicating a Single Metric widget
    Given a dashboard featuring a single Single Metric widget
    When the dashboard administrator user duplicates the Single Metric widget
    Then a second Single Metric widget is displayed on the dashboard
    And the second widget reports on the same metric as the first widget
    And the second widget has the same properties as the first widget

  @TEST_MON-23791
  Scenario: Editing the value format of a Single Metric widget
    Given a dashboard with a Single Metric widget displaying a human-readable value format
    When the dashboard administrator user updates the value format of the Single Metric widget to "raw value"
    Then the displayed value format for this metric has been updated from human-readable to exhaustive

  @TEST_MON-23789
  Scenario: Editing the thresholds of a Single Metric widget
    Given a dashboard containing a Single Metric widget
    When the dashboard administrator user updates the custom warning threshold to a value below the current value
    Then the widget is refreshed to make it look like the metric is in a warning state
    When the dashboard administrator user updates the custom critical threshold to a value below the current value
    Then the widget is refreshed to make it look like the metric is in a critical state

  @TEST_MON-23787
  Scenario: Editing the display type of a Single Metric widget
    Given a dashboard featuring a Single Metric widget
    When the dashboard administrator user changes the display type of the widget to a gauge
    Then the information reported by the widget is now displayed as a gauge
    When the dashboard administrator user changes the display type of the widget to a bar chart
    Then the information reported by the widget is now displayed as a bar chart

  @TEST_MON-23799
  Scenario: Deleting a Single Metric widget
    Given a dashboard featuring two Single Metric widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed