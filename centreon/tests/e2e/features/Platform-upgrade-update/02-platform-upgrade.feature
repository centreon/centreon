@execTimeout(300000)
@REQ_MON-22196 @system
Feature: Upgrade platform from major version A to major version B

  @TEST_MON-22198
  Scenario Outline: Administrator performs a platform update procedure
    Given a running platform in major '<major_from>' with '<version_from>' version
    When administrator updates packages to current version
    And administrator runs the update procedure
    Then monitoring should be up and running after update procedure is complete to current version
    And legacy services grid page should still work

    When administrator exports Poller configuration
    Then Poller configuration should be fully generated

    Examples:
      | major_from | version_from    |
      | n - 1      | last stable     |
      | n - 1      | last stable - 1 |
