@execTimeout(200000)
Feature: Update platform from from to version_to of the same MAJOR
An admin user can update a platform, from a version A to version B
which is higher than version A, within the same MAJOR.

Scenario: Administrator performs a platform update procedure
Given a running platform in '<version_from>'
When administrator updates packages to '<version_to>'
And administrator runs the update procedure
Then monitoring should be up and running after update procedure is complete to '<version_to>'

Examples:
  | version_from | version_to |
  |   22.10.0 |   22.10.7 |
