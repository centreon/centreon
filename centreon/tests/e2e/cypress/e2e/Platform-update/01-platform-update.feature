@execTimeout(120000)
Feature: Update platform from version_A to version_B of the same MAJOR
An admin user can update a platform, from a version A to version B
which is higher than version A, within the same MAJOR.

Background:
Given an admin user with valid non-default credentials
And a system user root

Scenario: Administrator performs a platform update procedure
Given a running platform in version_A
When administrator updates packages
And administrator runs the update procedure
Then monitoring should be up and running after procedure is complete

Scenario: Administrator performs Poller configuration export
Given a successfully updated platform
When administrator exports Poller configuration
Then Poller configuration should be fully generated