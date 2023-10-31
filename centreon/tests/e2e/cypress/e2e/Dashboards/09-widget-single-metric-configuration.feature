Feature: Configuring a single metric widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing single metric on a dashboard
  So that this dashboard can feature information users can read and links they can click

Scenario: Creating and configuring a new Generic text widget on a dashboard
  Given a dashboard in the dashboard administrator user's dashboard library
  When the dashboard administrator user selects the option to add a new widget
  And selects the widget type "Single metric"
  Then configuration properties for the Single metric widget are displayed
  When the dashboard administrator user selects a resource and the metric for the widget to report on
  Then information about this metric is displayed in the widget preview
  When the user saves the Single metric widget
  Then the Single metric widget is added in the dashboard's layout
  And the information about the selected metric is displayed

Scenario: Duplicating a Single metric widget
  Given a dashboard featuring a single Single metric widget
  When the dashboard administrator user duplicates the Single metric widget
  Then a second Single metric widget is displayed on the dashboard
  And the second widget reports on the same metric as the first widget
  And the second widget has the same properties as the first widget

Scenario: Editing the value format of a single metric widget
  Given a dashboard featuring a Single metric widget
  When the dashboard administrator user updates the value format of the single metric widget to "raw value"
  Then the displayed value format for this metric has been updated from human-readable to exhaustive

Scenario: Editing the thresholds of a Single metric widget
  Given a dashboard featuring a Single metric widget
  When the dashboard administrator user updates the custom warning threshold to a value below the current value
  Then the widget is refreshed to make it look like the metric is in a warning state
  When the dashboard administrator user updates the custom critical threshold to a value below the current value
  Then the widget is refreshed to make it look like the metric is in a critical state

Scenario: Editing the display type of a Single metric widget
  Given a dashboard featuring a Single metric widget
  When the dashboard administrator user changes the display type of the widget to a gauge
  Then the information reported by the widget is now displayed as a gauge
  When the dashboard administrator user changes the display type of the widget to a bar chart
  Then the information reported by the widget is now displayed as a bar chart

Scenario: Deleting a Single metric widget
  Given a dashboard featuring two Single metric widgets
  When the dashboard administrator user deletes one of the widgets
  Then only the contents of the other widget are displayed
