@REQ_MON-20198
Feature: Configuring a single text widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing simple text on a dashboard
  So that this dashboard can feature information users can read and links they can click

  @TEST_MON-22662
  Scenario: Creating and configuring a new Generic text widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And selects the widget type "Generic text"
    Then configuration properties for the Generic text widget are displayed
    When the dashboard administrator user gives a title to the widget and types some text in the properties' description field
    Then the same text is displayed in the widget's preview
    When the user saves the widget containing the Generic text
    Then the Generic text widget is added in the dashboard's layout
    And its title and description are displayed
  
  @TEST_MON-22661
  Scenario: Duplicating a Generic text widget
    Given a dashboard featuring a single Generic text widget
    When the dashboard administrator user duplicates the widget
    Then a second widget with identical content is displayed on the dashboard
  
  @TEST_MON-22664
  Scenario: Editing a Generic text widget
    Given a dashboard featuring two Generic text widgets
    When the dashboard administrator user updates the contents of one of these widgets
    Then the updated contents of the widget are displayed instead of the original ones

  @TEST_MON-22665
  Scenario: Deleting a Generic text widget
    Given a dashboard featuring two Generic text widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed
  
  @TEST_MON-22663
  Scenario: Hiding the description of a Generic text widget
    Given a dashboard featuring a single Generic text widget
    When the dashboard administrator user hides the description of the widget
    Then the description is hidden and only the title is displayed
  
  @TEST_MON-22660
  Scenario: Adding a clickable link in the description of a Generic text widget
    Given a dashboard featuring a single Generic text widget
    When the dashboard administrator user adds a clickable link in the contents of the widget
    Then the link is clickable on the dashboard view page and redirects to the proper website