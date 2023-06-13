Feature: Create a new dashboard
  As a Centreon User with dashboard edition rights, 
  I need to start creating a dashboard starting from an empty page on which I will place widgets
  So these dashboards can be consulted by myself and other users
  
Scenario: Create a new dashboard with name only
  Given a user with dashboard edition rights on the dashboard listing page
  When the user opens the form to create a new dashboard 
  Then the creation form is displayed and contains the fields to create a dashboard
  When the user fills in the name field
  Then the user is allowed to create the dashboard
  When the user saves the dashboard
  Then the newly created dashboard appears in the dashboards library

Scenario: Create a new dashboard with name and description
  Given a user with dashboard edition rights on the dashboard creation form
  When the user fills in the name and description fields and save
  Then the newly created dashboard appears in the dashboards library with its name and description

Scenario: Cancel a creation form
  Given a user with dashboard edition rights who is about to create a dashboard
  When the user leaves the creation form without saving the dashboard
  Then the dashboard has not been created when the user is redirected back on the dashboards library 
  When the user opens the form to create a new dashboard for the second time
  Then the information the user filled in the first creation form has not been saved