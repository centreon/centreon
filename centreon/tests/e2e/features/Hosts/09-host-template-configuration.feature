Feature: Edit host template
  As a Centreon user
  I want to access to host template configuration easily
  To edit configuration quickly

  Background:
    Given a user is logged in a Centreon server

  @TEST_MON-163494
  Scenario: Edit parent of a host
    When a host inheriting from a host template
    And the user configures the host
    Then the user can configure directly its parent template

  @TEST_MON-163495
  Scenario: Edit parent of a host template
    When a host template inheriting from a host template
    And the user configures the host template
    Then the user can configure directly its parent template
