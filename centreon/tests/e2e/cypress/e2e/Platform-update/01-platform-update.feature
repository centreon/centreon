@execTimeout(300000)
Feature: Update platform from from to version_to of the same MAJOR
  An admin user can update a platform, from a version A to version B
  which is higher than version A, within the same MAJOR.

Scenario: Administrator performs a platform update procedure
  Given a running platform in first minor version
  When administrator updates packages to current version
  And administrator runs the update procedure
  Then monitoring should be up and running after update procedure is complete to current version

Scenario: Administrator performs Poller configuration export
  Given a successfully updated platform
  When administrator exports Poller configuration
  Then Poller configuration should be fully generated
