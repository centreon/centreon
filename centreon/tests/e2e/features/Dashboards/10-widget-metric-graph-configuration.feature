Feature: Configuring metrics graph widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing a single metric on a dashboard
  To manipulate the properties of the Single Metric Widget and test the outcome of each manipulation.

Scenario: Creating and configuring a new Metrics Graph widget on a dashboard
  Given a dashboard in the dashboard administrator user's dashboard library
  When the dashboard administrator user selects the option to add a new widget
  And selects the widget type "Metrics graph"
  Then configuration properties for the Metrics graph widget are displayed
  When the dashboard administrator user selects a resource and a metric for the widget to report on
  Then a graph with a single bar is displayed in the widget's preview
  And this bar represents the evolution of the selected metric over the default period of time
  When the user saves the Metrics Graph widget
  Then the Metrics Graph widget is added to the dashboard's layout
  And the information about the selected metric is displayed


# Scenario: Editing the time period of the Metrics Graph widget
#  Given a dashboard featuring a configured Metrics Graph widget
#  When the dashboard administrator user selects another time period for the widget
#  Then the X-axis of the Metrics Graph widget is updated to reflect this change of time period

# Scenario: Editing the thresholds of a Metrics Graph widget
#  Given a dashboard featuring a configured Metrics Graph widget
#  When the dashboard administrator user updates the custom warning threshold
#  Then the Metrics Graph widget is refreshed to display the updated warning threshold horizontal bar
#  When the dashboard administrator user updates the custom critical threshold
#  Then the Metrics Graph widget is refreshed to display the updated critical threshold horizontal bar
#  When the dashboard administrator user updates a threshold to a value beyond the default range of the Y-axis
#  Then the Y-axis of the Metrics Graph widget is updated to reflect the change in threshold

# Scenario: Deleting a Metrics Graph widget
#   Given a dashboard featuring two Metrics Graph widgets
#   When the dashboard administrator user deletes one of the Metrics Graph widgets
#   Then only the contents of the other Metrics Graph widget are displayed

# Scenario: Duplicating a Metrics Graph widget
#   Given a dashboard featuring a configured Metrics Graph widget
#   When the dashboard administrator user duplicates the Metrics Graph widget
#   Then a second Metrics Graph widget is displayed on the dashboard
#   And the second widget has the same properties as the first widget

# Scenario: Customizing the time period of a Metrics Graph widget
#  Given a dashboard featuring a configured Metrics Graph widget
#  When the dashboard administrator user selects the option to have a customized time period
#  Then additional options to configure a customized time period are displayed
#  When the dashboard administrator user inputs a customized time period
#  Then the X-axis of the Metrics Graph widget is updated to reflect the customized time period

# Scenario: Adding new hosts in the Metrics Graph widget representation
#  Given a dashboard featuring a configured Metrics Graph widget
#  When the dashboard administrator user adds hosts with the same template as the initial one in the dataset selection
#  Then additional bars representing the metric behavior of these new hosts are added to the Metrics Graph widget

# Scenario: Adding new hosts in the Metrics Graph widget representation
#  Given a dashboard featuring a configured Metrics Graph widget
#  When the dashboard administrator user selects a metric with a different unit than the initial metric in the dataset selection
#  Then additional bars representing the metric behavior of these metrics are added to the Metrics Graph widget
#  And an additional Y-axis based on the unit of these additional bars is displayed
#  And the thresholds are automatically hidden
#  When the dashboard administrator user tries to select a new metric based on a third unit in the dataset selection
#  Then it is impossible for the dashboard administrator user to select it