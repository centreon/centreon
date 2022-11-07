Feature: Virtual Metric Handle
    As a Centreon user
    I want to use virtual metric
    To calculate specific values I need to check

    Background:
        Given I am logged in a Centreon server with configured metrics
<<<<<<< HEAD
   
    Scenario: Create a virtual metric
        When I add a virtual metric
        Then all properties are saved
        
=======

    Scenario: Create a virtual metric
        When I add a virtual metric
        Then all properties are saved

>>>>>>> centreon/dev-21.10.x
    Scenario: Duplicate a virtual metric
        Given an existing virtual metric
        When I duplicate a virtual metric
        Then all properties are copied except the name
<<<<<<< HEAD
        
=======

>>>>>>> centreon/dev-21.10.x
    Scenario: Delete a virtual metric
        Given an existing virtual metric
        When I delete a virtual metric
        Then the virtual metric disappears from the Virtual metrics list
