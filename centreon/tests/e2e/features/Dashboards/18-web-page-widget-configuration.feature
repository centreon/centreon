@REQ_MON-34256
Feature: Configuring web page widget
  As a Centreon User with dashboard update rights,
  I need to configure a web page widget on a dashboard
  To manipulate the properties of the web page Widget and test the outcome of each manipulation.

  @TEST_MON-147365
  Scenario: Creating and configuring a web page widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "web page"
    Then configuration properties for the web page widget are displayed
    When the dashboard administrator adds a valid URL
    And the user saves the web page widget
    Then the web page widget is added in the dashboard's layout

  @TEST_MON-147368
  Scenario: Deleting  web page widget
    Given a dashboard featuring two web page widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  @TEST_MON-147369
  Scenario: Duplicating a web page widget
    Given a dashboard having a configured web page widget
    When the dashboard administrator user duplicates the web page widget
    Then a second web page widget is displayed on the dashboard

  @TEST_MON-147370
  Scenario: Handling invalid URL input in the web page widget
    Given a dashboard having a configured web page widget
    When the dashboard administrator attempts to add an invalid URL
    Then an error message should be displayed, indicating that the URL is invalid
