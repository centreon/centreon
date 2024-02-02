@REQ_MON-21039
Feature: Configuring a top-bottom widget
  As a Centreon User with dashboard update rights,
  I need to configure a top-bottom widget containing on a dashboard
  So that this dashboard can feature information users can read and links they can click

  @TEST_MON-23800
  Scenario: Creating and configuring a new Top/Bottom widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And selects the widget type "Top Bottom"
    Then configuration properties for the Top Bottom widget are displayed
    When the dashboard administrator user selects a list of resources and the metric for the widget to report on
    Then a top of best-performing resources for this metbric are displayed in the widget preview
    When the user saves the Top Bottom widget
    Then the Top Bottom metric widget is added in the dashboard's layout

  @TEST_MON-23811
  Scenario: Editing hosts from the dataset selection of a Top/Bottom widget
    Given a dashboard configured with a Top Bottom widget
    When the dashboard administrator user removes a host from the dataset selection of the Top Bottom widget
    Then the bar associated with the host is removed from the Top Bottom widget preview
    When the dashboard administrator user adds a host from the dataset selection of the Top Bottom widget
    Then the bar associated with the host is added in the Top Bottom widget preview

  @TEST_MON-23813
  Scenario: Duplicating a Top/Bottom widget
    Given a dashboard having a configured Top Bottom widget
    When the dashboard administrator user duplicates the Top Bottom widget
    Then a second Top Bottom widget is displayed on the dashboard

  @TEST_MON-23814
  Scenario: Deleting a Top/Bottom widget
    Given a dashboard featuring two Top Bottom widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  @TEST_MON-23807
  Scenario: Hiding the value labels of a Top/Bottom widget
    Given a dashboard with a configured Top Bottom widget
    When the dashboard administrator user selects the option to hide the value labels
    And the value labels for all hosts in the Top Bottom widget are hidden in view mode

  @TEST_MON-23808
  Scenario: Editing the value format of a Top/Bottom widget
    Given a dashboard containing a Top Bottom widget
    When the dashboard administrator user updates the value format of the Top Bottom widget to "raw value"
    Then the displayed value format for this metric has been updated from human-readable to exhaustive

  @TEST_MON-23809
  Scenario: Editing the thresholds of a Top/Bottom widget
    Given a dashboard featuring a configured Top Bottom widget
    When the dashboard administrator user updates the custom warning threshold
    Then the widget is refreshed to display the updated warning threshold on all bars of the Top Bottom widget
    When the dashboard administrator user updates the custom critical threshold
    Then the widget is refreshed to display the updated critical threshold on all bars of the Top Bottom widget
