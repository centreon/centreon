Feature: Navigate through the list of dashboards
  As a Centreon user with appropriate rights 
  I need to navigate through the list of available dashboards 
  In order to locate the one I want to display or edit

Scenario: Get on the listing page when no dashboards are available  
  Given a user with access to the dashboards library
  When they access the dashboard listing page on a platform with no dashboards
  And a special message and a button to create a new dashboard are displayed instead of the dashboards

Scenario: Select an immediately visible dashboard in a list of dashboards 
  Given a non-empty list of dashboards that fits on one page
  When the user clicks on the dashboard they want to select 
  Then they are redirected to the information page for that dashboard