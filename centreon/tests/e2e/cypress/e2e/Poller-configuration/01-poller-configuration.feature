Feature: Generate poller configuration
    As a Centreon user
    I want to generate the poller configuration
    So that the changes made in the configuration are deployed on my platform

Background:
   Given I am granted the rights to access the poller page and export the configuration
   And I am logged in
   And I the platform is configured with some resources
   And some pollers are created
   And some post-generation commands are configured for each poller

  Scenario: Generate the configuration on mutliple pollers
        When I visit the export configuration page
        Then there is an indication that the configuration have changed on the listed pollers
        When I select some pollers
        And I click on the Export configuration button
        Then I am redirected to generate page
        And the selected poller names are displayed
        When I select all action checkboxes
        And I select the '<method>' export method
        When I click on the export button
        Then the configuration is generated on selected pollers
        And the selected pollers are '<poller_action>'

      Examples:
        | method    | poller_action |
        | Reload    | reloaded      |
        | Restart   | restarted     |

   Scenario: Generate the configuration with no poller selected
         When I visit the export configuration page
         And I click on the Export configuration button
         Then I am redirected to generate page
         And no poller names are displayed
         When I click on the export button
         Then an error message is displayed to inform that no poller is selected

   Scenario: Generate the configuration to all pollers quickly
         When I click on the export configuration action and confirm
         Then a success message is displayed
         And the configuration is generated on all pollers

   Scenario: Generate the configuration with broken pollers
         Given broken pollers
         When I visit the export configuration page
         And I select some pollers
         And I click on the Export configuration button
         Then I am redirected to generate page
         When I click on the export button
         Then the configuration is not generated on selected pollers