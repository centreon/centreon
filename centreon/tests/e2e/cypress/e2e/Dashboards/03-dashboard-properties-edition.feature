Feature: Edit dashboard properties
  As a Centreon User with dashboard update rights
  I need to edit the properties of a dashboard
  In order to set parameters that will prescribe the behavior of this dashboard

Scenario: Update a dashboard in the dashboards library
  Given a user with update rights on a dashboard featured in the dashboards library
  When the user selects the properties of the dashboard
  Then the update form is displayed and contains fields to update this dashboard
  When the user fills in the name and description fields with new compliant values
  Then the user is allowed to update the dashboard
  When the user saves the dashboard with its new values
  Then the dashboard is listed in the dashboards library with its new name and description

Scenario: Cancel an update form
  Given a user with dashboard update rights who is about to update a dashboard with new values
  When the user leaves the update form without saving
  Then the dashboard has not been edited and features its former values
  When the user opens the form to update the dashboard for the second time
  Then the information the user filled in the first update form has not been saved

Scenario: Empty a dashboard's name field
  Given a user with dashboard update rights in a dashboard update form
  When the user sets an empty name for this dashboard
  Then the user cannot save the dashboard in its current state
  When the user enters a new name for this dashboard
  Then the user can now save the dashboard

Scenario: Empty a dashboard's description field
  Given a user with dashboard update rights in the update form of a dashboard with description
  When the user sets an empty description for this dashboard
  Then the user can save the dashboard with an empty description
  When the user saves the dashboard with the description field empty
  Then the dashboard is listed in the dashboard library with only its name