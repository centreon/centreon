Feature: Configuring a single text widget
  As a Centreon User with dashboard update rights,
  I need to configure a widget containing simple text on a dashboard
  So that this dashboard can feature information users can read and links they can click

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

Scenario: Copying a Generic text widget
  Given a dashboard containing a Generic text widget
  When the dashboard administrator user duplicates the widget
  Then a second widget with identical content is displayed on the dashboard

Scenario: Editing a Generic text widget
  Given a dashboard containing Generic text widgets
  When the dashboard administrator user updates the contents of one of these widgets
  Then the updated contents of the widget are displayed instead of the original ones

Scenario: Deleting a Generic text widget
  Given a dashboard featuring two Generic text widgets
  When the dashboard administrator user deletes one of the widgets
  Then only the contents of the other widget are displayed