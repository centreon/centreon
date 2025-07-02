Feature: Limit metrics in chart
  As a Centreon Web user
  I want to know if there might be issue with a chart with a lot of metrics
  So that i will not crash my browser

  Background:
    Given a user is logged in a Centreon server
    And many virtual metrics are linked to a configured service

  @TEST_MON-161643
  Scenario: Display message and button in performance page
    When the user displays the chart in performance page
    Then a message says that the chart will not be displayed is visible
    And a button is available to display the chart