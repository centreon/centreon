Feature: import export with interface
    As a Centreon Web user
    I want to export import my objects with interface

    Background:
        Given I am logged in a Centreon server with Awie installed

    @critical
    Scenario: export
        When I export an object
        Then I have a file
