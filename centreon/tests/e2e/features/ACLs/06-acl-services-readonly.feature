@REQ_MON-200027
Feature: Read-only users get a listing without write controls
  As a Centreon administrator
  I want the migrated listings to drop their write controls for a read-only user
  So that nobody is offered an action the backend will refuse

  # Two families on purpose: service groups are rendered by the shared framework,
  # while services by host carries its own copy of the same read-only rewrite.
  # The copies can drift, and only running both catches it.

  Background:
    Given a read-only user is logged in

  # Cloud: identical
  Scenario: The service groups listing hides its write controls
    When the read-only user opens the service groups listing
    Then the seeded service group is listed
    And the listing offers no add button and no bulk actions
    And the row toggles are inert and carry no duplication field

  # Cloud: identical
  Scenario: The services by host listing hides its write controls
    When the read-only user opens the services by host listing
    Then the seeded service is listed
    And the listing offers no add button and no bulk actions
    And the row toggles are inert and carry no duplication field

  # Cloud: identical
  Scenario: The columns stay aligned without the write controls
    When the read-only user opens the service groups listing
    Then the seeded service group is listed
    And every row holds as many cells as the header holds columns

  # The rendering assertions above only prove the UI hides the control. This one
  # proves the endpoint refuses the write, which is what the title claims.
  # Cloud: identical
  Scenario: The toggle endpoint refuses a read-only user
    When the read-only user opens the services by host listing
    And the read-only user posts a toggle for the listed service
    Then the toggle endpoint refuses the write
    And the listed service is still enabled in the database
