Feature: AliasContactModification
  As a Centreon admin user
  I want to modify an existing non admin contact alias including a special character
  So that the Modified contact is saved
  And the modified contact can log in Centreon Web

  Background:
    Given an admin user is logged in a Centreon server
    And one non admin contact has been created

  @TEST_MON-152126
  Scenario: Modify contact alias by adding an accent or a special character
    When the user has changed the contact alias by adding a special character
    Then the new record is displayed in the users list with the new alias value

  @TEST_MON-152127
  Scenario: Check modified contact by adding an accent to the alias still able to log in Centreon Web
    Given the contact alias contains an accent
    When the contact fill login field and Password
    Then the contact is logged in to Centreon Web