Feature: Actions on Resources
  As a user
  I want to apply actions on the Resources
  So that my IT infrastructure stays sane

<<<<<<< HEAD
  Scenario: Acknowledging a problematic Resource
    When I select the acknowledge action on a problematic Resource
    Then the problematic Resource is displayed as acknowledged

  Scenario: Setting a downtime on a problematic Resource
    When I select the downtime action on a problematic Resource
    Then the problematic Resource is displayed as in downtime
=======
  Scenario: I can acknowledge a problematic Resource
    When I select the acknowledge action on a problematic Resource
    Then The problematic Resource is displayed as acknowledged

  Scenario: I can set a downtime on a problematic Resource
    When I select the downtime action on a problematic Resource
    Then The problematic Resource is displayed as in downtime
>>>>>>> centreon/dev-21.10.x
