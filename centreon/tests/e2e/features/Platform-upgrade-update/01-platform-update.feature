@execTimeout(300000)
@REQ_MON-22132 @system @ignore
Feature: Update platform from version A to version B of the same MAJOR
  An admin user can update a platform, from a version A to version B
  which is higher than version A, within the same MAJOR.

  @TEST_MON-22197
  Scenario Outline: Administrator performs a platform update procedure
    Given a running platform in '<version_from>' version
    When administrator updates packages to current version
    And administrator runs the update procedure
    Then monitoring should be up and running after update procedure is complete to current version
    And legacy services grid page should still work

    When administrator exports Poller configuration
    Then Poller configuration should be fully generated

    Examples:
      | version_from           |
      | first minor            |
      | last stable            |
      | penultimate stable     |
      | antepenultimate stable |
