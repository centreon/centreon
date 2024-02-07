@REQ_MON-24518
Feature: Configuring resource table widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a resource table on a dashboard
  To manipulate the properties of the resource table Widget and test the outcome of each manipulation.

  @TEST_MON-35094
  Scenario: Editing the displayed resource status of ressource table widget
    Given a dashboard that includes a configured ressource table widget
    When the dashboard administrator user selects a particular status in the displayed resource status list
    Then only the resources with this particular status are displayed in the ressource table Widget

 @TEST_MON-35096
  Scenario: Displaying ressources on a downtime on a ressource table widget
    Given a dashboard featuring a configured ressource table widget
    When the dashboard administrator user applies a downtime on one of the resources of the dataset selection
    And selects the option to exclusively display the resources on a downtime
    Then only the resource on a downtime is displayed in the ressrouce table widget

  @TEST_MON-35098
  Scenario: Duplicating ressource table widget
    Given a dashboard having a configured ressrouce table widget
    When the dashboard administrator user duplicates the ressource table widget
    Then a second ressrouce table widget is displayed on the dashboard
    And the second widget has the same properties as the first widget

  @TEST_MON-35084
  Scenario: Creating and configuring a new ressource table widget on a dashboard
    Given a dashboard in the dashboard administrator user's dashboard library
    When the dashboard administrator user selects the option to add a new widget
    And selects the widget type "Ressource table"
    Then configuration properties for the ressource table widget are displayed
    When the dashboard administrator user selects a resource and a metric for the widget to report on
    When the user saves the ressource table widget
    Then the ressource table widget is added to the dashboard's layout
    And the information about the selected metric is displayed

  @TEST_MON-35099
  Scenario: Editing the number of displayed tiles on a ressource table widget
    Given a dashboard featuring two Metrics ressrouce tables
    When the dashboard administrator user deletes one of the ressource table widgets
    Then only the contents of the other ressource table h widget are displayed

  @TEST_MON-35095
  Scenario: Editing the displayed resource state of a ressource table widget
   Given a dashboard that includes a configured ressource table widget
   When the dashboard administrator user selects a particular status in the displayed ressrouce state list
   Then only the resources with this particular state are displayed in the ressource table Widget

  @TEST_MON-35097
  Scenario: Displaying aknowledged ressources on a ressource table widget
   Given a dashboard featuring a configured ressrouce table widget
   When the dashboard administrator user applies an acknowledgement on one of the resources of the dataset selection
   And selects the option to exclusively display the acknowledged resources
   Then only the acknowledged resource is displayed in the ressrouce table widget
