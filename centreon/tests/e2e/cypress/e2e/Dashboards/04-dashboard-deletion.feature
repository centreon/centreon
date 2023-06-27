Feature: Delete an existing dashboard
  As a Centreon User with dashboard update rights,
  I need to be able to delete a dashboard that has become obsolete or unnecessary,
  So that the dashboards library is not overburden

Scenario: Delete a dashboard on the dashboards library
  Given a user with dashboard update rights on the dashboards library
  When the user clicks on the delete button for a dashboard featured in the library
  Then a confirmation pop-up appears
  When the user confirms the choice to delete the dashboard
  Then the dashboard is not listed anymore in the dashboards library