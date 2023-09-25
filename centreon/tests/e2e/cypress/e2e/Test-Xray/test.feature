#targetVersions:OnPrem - 23.10,OnPrem - 21.10,Cloud,Connectors,OnPrem - 23.04,OnPrem - 22.04,OnPrem - 22.10,22.10 components:centreon-web testSet:MON-21774
@MON-21779
Feature: Automated feature ( Test ) 
    As a Centreon Web user 
	I want to login
	In order to access selected pages

    #this is a description for test 1
    @id:1 @LABEL-TEST-1
    Scenario: Test : Logging in
    When I enter my credentials on the login page
    Then I am redirected to the default page

    #this is a description for test 2
    @id:2 @LABEL-TEST-2
    Scenario: Test : Logging out
    Given I am logged in
    When I click on the logout action
    Then I am logged out and redirected to the login page