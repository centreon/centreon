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