# TODO: add @REQ_MON-<id> once the functional requirement for downtime DST is identified
@system
Feature: Recurrent downtime around DST transitions
  As a Centreon user
  I want recurrent downtimes to be scheduled correctly across daylight saving time
  So that maintenance windows keep their intended duration when clocks change

  Background:
    Given a user logged in with the Europe/Paris timezone
    And a passive service is monitored

  # Spring forward — clocks jump 02:00 -> 03:00, so 02:00-03:00 does not exist (23h day)

  Scenario: recurrent downtime starting on a non-existent spring-forward time is clamped
    When a recurrent downtime starting at the non-existent spring-forward time is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime ending on a non-existent spring-forward time is clamped
    When a recurrent downtime ending at the non-existent spring-forward time is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime fully inside the spring-forward gap is not scheduled
    When a recurrent downtime fully inside the spring-forward gap is applied
    Then the downtime is not scheduled

  Scenario: recurrent downtime over a full spring-forward day lasts 23h
    When a recurrent downtime covering the whole spring-forward day is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime on the day after the spring-forward transition lasts 24h
    When a recurrent downtime covering the day after the spring-forward transition is applied
    Then the scheduled downtime matches the expected start, end and duration

  # Fall back — clocks go 03:00 -> 02:00, so 02:00-03:00 happens twice (25h day)

  Scenario: recurrent downtime starting in the repeated fall-back hour is scheduled as entered
    When a recurrent downtime starting in the repeated fall-back hour is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime ending in the repeated fall-back hour is scheduled as entered
    When a recurrent downtime ending in the repeated fall-back hour is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime fully inside the repeated fall-back hour is scheduled
    When a recurrent downtime fully inside the repeated fall-back hour is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime over a full fall-back day lasts 25h
    When a recurrent downtime covering the whole fall-back day is applied
    Then the scheduled downtime matches the expected start, end and duration

  Scenario: recurrent downtime on the day after the fall-back transition lasts 24h
    When a recurrent downtime covering the day after the fall-back transition is applied
    Then the scheduled downtime matches the expected start, end and duration
