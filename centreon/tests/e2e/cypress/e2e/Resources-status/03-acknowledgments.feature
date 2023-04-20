Feature: Add an acknowledgement on a resource with a problem
    As a user
    I would like to be able to add an acknowledgement on a problematic resource
    So that the users of the platform do not receive any more notifications about the problem until acknowledgement is terminated.

Background:
    Given the user have the necessary rights to page Ressource Status
    And the user have the necessary rights to acknowledge & disacknowledge
    And there are at least two resources of each type with a problem and notifications enabled for the user