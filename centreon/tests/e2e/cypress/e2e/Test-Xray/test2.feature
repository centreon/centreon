@REQ_MON-22231
Feature: Automated feature ( Test ) 2
    As a Centreon Web user 
	I want to login
	In order to access selected pages

    #this is a description for test 1
    @id:1 @LABEL-TEST-1
    Scenario: Test : Logging in 2 
    When I enter my credentials on the login page
    Then I am redirected to the default page

    #this is a description for test 2
    @id:2 @LABEL-TEST-2
    Scenario: Test : Logging out 2
    Given I am logged in
    When I click on the logout action
    Then I am logged out and redirected to the login page