@ignore
@REQ_MON-22568
Feature: Login: new-demo

Background: Background Title
 Given .....
 And ......

#this is a description for test 1
@LABEL-TEST-1
Scenario: Logging in: new-demo
  When I enter my credentials on the login padge
  Then I am redirected to the default page

#this is a description for test 2
@LABEL-TEST-2
Scenario: Logging out: new-demo
  Given I am logged in
  When I click on the logout action
  Then I am logged out and redirected to the login page
