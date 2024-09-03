Feature: Configuring web page widget
  As a Centreon User with dashboard update rights,
  I need to configure a Web page widget on a dashboard
  To manipulate the properties of the Web page Widget and test the outcome of each manipulation.

  Scenario: Creating and configuring a Web page widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And the dashboard administrator user selects the widget type "Web page"
    Then configuration properties for the Web page widget are displayed
    When the dashboard administrator adds a valid URL
    And the user saves the Web page widget
    Then the Web page widget is added in the dashboard's layout

  Scenario: Deleting  Web page widget
    Given a dashboard featuring two Web page widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  Scenario: Duplicating a Web page widget
    Given a dashboard having a configured Web page widget
    When the dashboard administrator user duplicates the Web page widget
    Then a second Web page widget is displayed on the dashboard
