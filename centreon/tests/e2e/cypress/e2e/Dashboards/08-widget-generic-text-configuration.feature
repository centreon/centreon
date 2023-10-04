Feature: Configuring a single text widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing simple text on a dashboard
  So that this dashboard can feature information users can read and links they can click

Scenario: Creating and configuring a new Generic text widget on a dashboard
  Given a dashboard in the dashboard administrator user's dashboard library
  When the dashboard administrator user selects the option to add a new widget
  And selects the widget type "Generic text"
  Then configuration properties for the Generic Text widget are displayed
  And no preview is displayed for this widget
  When the dashboard administrator user gives a title to the widget and types some text in the properties' description field
  Then the same text is displayed in the widget's preview
  When the user saves the widget containing the Generic Text
  Then the Generic text widget is added in the dashboard's layout
  And its title and description are displayed