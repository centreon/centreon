Feature: Create a new dashboard
  As a Centreon User with dashboard edition rights, 
  I need to start creating a dashboard starting from an empty page on which I will place widgets
  So these dashboards can be consulted by myself and other users
  
Scenario: Create a new dashboard without template or category
  Given a user with dashboard edition rights on the dashboard listing page
  When they open the form to create a new dashboard 
  Then the creation form is displayed and contains the fields to create a dashboard
  When they fill in the mandatory fields of the form
  Then they are allowed to create the dashboard
  When they save the dashboard
  Then the newly created dashboard appears in the dashboards library