#testSet:MON-22561
@ignore @REQ_MON-22563
Feature: Login: add-to-test-set-demo

@LABEL-TEST-1
Scenario: Logging in: add-to-test-set-demo
  When I enter my credentials on the login page
  Then I am redirected to the default page
  
@LABEL-TEST-2
Scenario: Logging out: add-to-test-set-demo
  Given I am logged in
  When I click on the logout action
  Then I am logged out and redirected to the login page
