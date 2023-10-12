@ignore @REQ_MON-22562
Feature: Login: update-demo

@LABEL-TEST-1
Scenario: Logging in: update-demo 
  When I enter my credentials on the login page 111111
  Then I am redirected to the default page

@LABEL-TEST-UPDATED
Scenario: Logging out: update-demo
  Given I am logged in
  When I click on the logout action  3232333
  Then I am logged out and redirected to the login page
