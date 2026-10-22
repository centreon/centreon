Feature: Legacy configuration forms still work after the framework removal
  As a Centreon admin
  I want the legacy configuration forms to keep rendering their select2 fields
  So that removing the abandoned listing/form framework did not break them

  Background:
    Given an admin user is logged in a Centreon server

  Scenario: The legacy host add form still renders its select2 fields
    When the user opens the legacy host add form
    Then the host form renders its select2 fields
