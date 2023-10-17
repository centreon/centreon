@REQ_MON-23350
Feature: This is just a test

Background: This is the title of the background
    Given .....
    And .....

#this is a description for test 1
@LABEL-TEST-1
Scenario: Test : Logging in
    When I enter my credentials on the login page
    Then I am redirected to the default page

#this is a description for test 2
@LABEL-TEST-2
Scenario: Test : Logging out
    Given I am logged in
    When I click on the logout action
    Then I am logged out and redirected to the login page