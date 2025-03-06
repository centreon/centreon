Feature: Virtual Metric Handle
  As a Centreon administrator
  I want to use virtual metric
  To calculate specific values I need to check

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-152581
  Scenario: Create a virtual metric
    When the user adds a virtual metric
    Then all properties are saved

  @TEST_MON-152582
  Scenario: Edit one existing virtual metric
    Given an existing virtual metric
    When the user changes the properties of the configured virtual metric
    Then these properties are updated

  @TEST_MON-152583
  Scenario: Duplicate one existing virtual metric
    Given an existing virtual metric
    When the user duplicates the configured virtual metric
    Then a new virtual metric is created with identical fields

  @TEST_MON-152584
  Scenario: Delete one existing virtual metric
    Given an existing virtual metric
    When the user deletes the configured virtual metric
    Then the virtual metric disappears from the Virtual metrics list