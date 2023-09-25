Feature: Notification rule creation

  Background:
    Given a user accessing to listing of cloud notification definition
    And clicking on create a notification button
    Then the user should see the form option for rule creation

  Scenario: Creating a notification rule
    Given the user defines a name for the rule
    And the user selects one or more host groups and host status
    And the user selects one or more service groups and services status
    And the user selects one or more contacts
    And the user selects one or more contact groups
    And the user defines a mail subject
    And the user defines a mail body
    When the user clicks on the "Save" button and confirm
    Then a success message is displayed and the created notification rule is displayed in the listing