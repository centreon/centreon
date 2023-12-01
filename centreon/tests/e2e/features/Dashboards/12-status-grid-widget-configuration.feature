@REQ_MON-24518
Feature: Configuring status grid widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a status grid on a dashboard
  To manipulate the properties of the status grid Widget and test the outcome of each manipulation.

  # @TEST_MON-24937
  # Scenario: Editing the displayed resource status of a Status Grid widget
  #   Given a dashboard featuring a configured Status Grid widget
  #   When the dashboard administrator user selects a particular status in the displayed resource status list
  #   Then only the resources with this particular status are displayed in the Status Grid Widget
  #   When the dashboard administrator user selects every available status in the list
  #   Then every resource of the selection is displayed in the Status Grid widget

  # @TEST_MON-24936
  # Scenario: Editing the displayed resource type of a Status Grid widget
  #   Given a dashboard featuring a configured Status Grid widget
  #   When the dashboard administrator user updates the displayed resource type of the widget
  #   Then the list of available statuses to display is updated in the configuration properties
  #   And the widget is updated to reflect that change in displayed resource type

  # @TEST_MON-24938
  # Scenario: Displaying resources on a downtime on a Status Grid widget
  #   Given a dashboard featuring a configured Status Grid widget
  #   When the dashboard administrator user applies a downtime on one of the resources of the dataset selection
  #   And selects the option to exclusively display the resources on a downtime
  #   Then only the resource on a downtime is displayed in the Status Grid widget

  # @TEST_MON-24942
  # Scenario: Editing the sorting of the displayed resources of a Status Grid widget
  #   Given a dashboard featuring a configured Status Grid widget
  #   When the dashboard administrator user selects the option to sort the displayed resources by name
  #   Then the displayed resources are sorted by name in the Status Grid widget
  #   When the dashboard administrator user selects the option to sort the displayed resources by status
  #   Then the displayed resources are sorted by status in the Status Grid widget

  # @TEST_MON-24945
  Scenario: Deleting a Status Grid widget
    Given a dashboard featuring two Status Grid widgets
    When the dashboard administrator user deletes one of the widgets
    Then only the contents of the other widget are displayed

  # @TEST_MON-24935
  # Scenario: Creating and configuring a new Status Grid widget on a dashboard
  #   Given a dashboard in the dashboard administrator user's dashboard library
  #   When the dashboard administrator user selects the option to add a new widget
  #   And selects the widget type "Status Grid"
  #   Then configuration properties for the Status Grid widget are displayed
  #   When the dashboard administrator user selects a list of resources for the widget
  #   Then a grid representing the statuses of this list of resources are displayed in the widget preview
  #   When the user saves the Status Grid widget
  #   Then the Status Grid widget is added in the dashboard's layout

  # @TEST_MON-24941
  # Scenario: Displaying acknowledged resources on a Status Grid widget
  #   Given a dashboard featuring a configured Status Grid widget
  #   When the dashboard administrator user applies an acknowledgement on one of the resources of the dataset selection
  #   And selects the option to exclusively display the acknowledged resources
  #   Then only the acknowledged resource is displayed in the Status Grid widget

  # @TEST_MON-24943
  # Scenario: Editing the number of displayed tiles on a Status Grid widget
  #   Given a dashboard with a configured Status Grid widget
  #   When the dashboard administrator user updates the maximum number of displayed tiles in the configuration properties
  #   Then the Status Grid widget displays up to that number of tiles

  # @TEST_MON-24944
  # Scenario: Duplicating a Status Grid widget
  #   Given a dashboard having a configured Status Grid widget
  #   When the dashboard administrator user duplicates the Status Grid widget
  #   Then a second Status Grid widget is displayed on the dashboard
  #   And the second widget has the same properties as the first widget
