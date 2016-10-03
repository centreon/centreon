Feature: downtime start and stop
  As a Centreon user
  I want to be certain that the downtimes work correctly
  To release quality products

  Background:
    Given I am logged in a Centreon server
    And a passive service is monitored

#  Scenario: Configure downtime
#    Given a downtime in configuration of a user in other timezone
#    When I save a downtime
#    Then the time of the start and end of the downtime took into account the timezone of the supervised element

#  Scenario: Start of fixed downtime
#    Given a fixed downtime on a monitored element
#    When the downtime period is started
#    Then the downtime is started

#  Scenario: End of fixed downtime
#    Given a fixed downtime on a monitored element
#    And the downtime is started
#    When the end date of the downtime happens
#    Then the downtime is stopped

#  Scenario: Start of flexible downtime
#    Given a flexible downtime on a monitored element
#    And the downtime period is started
#    When the monitored element is not OK
#    Then the downtime is started

#  Scenario: End of flexible downtime
#    Given a flexible downtime on a monitored element
#    And the flexible downtime is started
#    When the downtime duration is finished
#    Then the downtime is stopped

  Scenario: Configure recurrent downtime
    Given a recurrent downtime on an other timezone service
    When this one gives a downtime
    Then the time of the start and end of the downtime took into account the timezone of the supervised element
