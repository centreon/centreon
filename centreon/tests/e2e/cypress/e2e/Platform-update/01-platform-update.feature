@execTimeout(120000)
Feature: Update platform from version_A to version_B of the same MAJOR
An admin user can update a platform, from a version A to version B
which is higher than version A, within the same MAJOR.

#Background:
#Given an admin user with valid non-default credentials
#And a system user root

Scenario: Administrator performs a platform update procedure
Given a running platform in '<version_A>' with all extensions installed
And this platform has existing configuration for all the installed extensions
When administrator updates packages to '<version_B>'
And administrator runs the update procedure
Then monitoring should be up and running after procedure is complete

Examples:
  | version_A | version_B |
  |   22.10.0 |   22.10.8 |
#  |   22.10.1 |   22.10.8 |
#  |   22.10.2 |   22.10.8 |
#  |   22.10.3 |   22.10.8 |
#  |   22.10.4 |   22.10.8 |
#  |   22.10.5 |   22.10.8 |
#  |   22.10.6 |   22.10.8 |
#  |   22.10.7 |   22.10.8 |

Scenario: User updates existing configuration and resources
Given a successfully updated platform
And this platform has existing pre-update procedure configuration and resources for all the installed extensions
When user updates the configuration for these extensions
Then the updated configuration and resources should be saved

Scenario: User creates new configuration and resources
Given a successfully updated platform
When user creates new configuration and resources for extensions
Then the new configuration and resources should be saved

Scenario: Administrator performs Poller configuration export
Given a successfully updated platform
When administrator exports Poller configuration
Then Poller configuration should be fully generated