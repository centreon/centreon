Feature: Navigate through the list of dashboards
  As a Centreon user with appropriate rights 
  I need to navigate through the list of available dashboards 
  In order to locate the one I want to display or edit
  
Scenario: Get on the listing page when no dashboards are available  
  Given a user with access to the dashboards library
  When the user accesses the dashboard listing page on a platform with no dashboards
  Then a message and a button to create a new dashboard are displayed instead of the dashboards
  
Scenario: Select a dashboard on the first page of the dashboard library 
  Given a non-empty list of dashboards that fits on a single page
  When the user clicks on the dashboard they want to select 
  Then the user is redirected to the information page for this dashboard 
