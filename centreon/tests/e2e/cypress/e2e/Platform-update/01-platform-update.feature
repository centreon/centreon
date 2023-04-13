@execTimeout(200000)
Feature: Update platform from version_A to version_B of the same MAJOR
An admin user can update a platform, from a version A to version B
which is higher than version A, within the same MAJOR.

Scenario: Administrator performs a platform update procedure
Given a running platform in '<version_A>'
When administrator updates packages to '<version_B>'
And administrator runs the update procedure
Then monitoring should be up and running after update procedure is complete to '<version_B>' 

Examples:
  | version_A | version_B |
  |   22.10.0 |   22.10.7 |
  |   22.10.1 |   22.10.7 |
  |   22.10.2 |   22.10.7 |
  |   22.10.3 |   22.10.7 |
  |   22.10.4 |   22.10.7 |
  |   22.10.5 |   22.10.7 |
  |   22.10.6 |   22.10.7 |

Scenario: Administrator performs Poller configuration export
Given a successfully updated platform
When administrator exports Poller configuration
Then Poller configuration should be fully generated