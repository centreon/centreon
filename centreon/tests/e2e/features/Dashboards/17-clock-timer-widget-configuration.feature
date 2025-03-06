@REQ_MON-144611
Feature: Configuring clock timer widget
  As a Centreon User with dashboard update rights,
  I need to configure a clock timer widget on a dashboard
  To manipulate the properties of the Clock timer Widget and test the outcome of each manipulation.

  @TEST_MON-146790
  Scenario: Creating and configuring a Clock timer widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Clock timer"
    Then configuration properties for the Clock timer widget are displayed
    When the user saves the Clock timer widget
    Then the Clock timer widget is added in the dashboard's layout

  @TEST_MON-146794
  Scenario: Set a New Time Zone for the Clock timer Widget
    Given a dashboard with a Clock Timer widget
    When the dashboard administrator updates the time zone by selecting a new one
    Then the timezone should be updated in the widget

  @TEST_MON-146795
  Scenario: Set a New Time Format for the Clock Timer Widget
    Given a dashboard with a Clock Timer widget
    When the dashboard administrator updates the time format by selecting a new one
    Then the time format in the widget should be updated to reflect the new format

  @TEST_MON-146797
  Scenario: Update Clock Timer Display from Clock to Timer
    Given a dashboard with a Clock Timer widget
    When the dashboard administrator changes the display setting of the Clock Timer widget from "Clock" to "Timer"
    Then the countdown input should be displayed
    When the dashboard administrator updates the countdown input
    Then the widget should display the "Timer" format

  @TEST_MON-146798
  Scenario: Duplicating a Clock timer widget
    Given a dashboard with a Clock Timer widget
    When the dashboard administrator user duplicates the Clock timer widget
    Then a second Clock timer widget is displayed on the dashboard

  @TEST_MON-146799
  Scenario: Update the Background Color of the Clock Timer Widget
    Given a dashboard with a Clock Timer widget
    When the dashboard administrator updates the background color of the Clock Timer widget
    Then the background color of the Clock Timer widget should reflect the updated color
