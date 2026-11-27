Feature: CSRF protection of legacy configuration forms
  As a Centreon administrator
  I want a legacy configuration form to accept a submission only with the single-use token it issued
  So that a forged or replayed request cannot change the configuration on my behalf

  @MON-214329
  Scenario: A legacy configuration form rejects a submission without a valid CSRF token
    Given an administrator has created the host rbt-csrf-host1 through the legacy host form
    When the captured form submission is replayed with its already used centreon_token
    Then the form is refused with the invalid token message and no host is created
    When the submission is replayed without the centreon_token field
    Then the form is refused and no host is created
    When the submission is replayed with a forged centreon_token
    Then the form is refused and no host is created
