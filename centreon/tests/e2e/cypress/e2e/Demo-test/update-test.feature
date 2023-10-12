@ignore @REQ_MON-22562
Feature: Login: update-demo

@LABEL-TEST-1
Scenario: Logging in: update-demo
  When I enter my credentials on the login page
  Then I am redirected to the default page

@LABEL-TEST-2
Scenario: Logging out: update-demo
  Given I am logged in
  When I click on the logout action
  Then I am logged out and redirected to the login page
