Feature: Local authentication
    As a user
    I want to be able to manage password security policies on a Centreon platform for users going through local authentication
    So that platform administrators can rely on better password practices and and increased security
    
Scenario: Default password policy
    Given an administrator deploying a new Centreon platform
    When the administrator opens the authentication configuration menu
    Then a default password policy and default excluded users must be present

Scenario: Enforcing a password case policy
    Given an administrator configuring a Centreon platform and an existing user account
    When the administrator sets a valid password length and sets all the letter cases
    Then the existing user can not define a password that does not match the password case policy defined by the administrator and is notified about it

Scenario: Enforcing a password expiration policy
    Given an administrator configuring a Centreon platform and an existing user account with password up to date
    When the administrator sets valid password expiration policy durations in password expiration policy configuration and the user password expires
    Then the existing user can not authenticate and is notified about it
    
Scenario: Enforcing password change policy
    Given an administrator configuring a Centreon platform and an existing user account with a first password
    When the administrator enables the password reuseability and a user attempts to change its password multiple times in a row
    Then user can not change password unless the minimum time has passed
    Then user can not reuse the last passwords more than 3 times
    
Scenario: Editing the excluded users list
    Given an existing password policy configuration and 2 non admin users
    When the administrator adds or remove a user from the excluded user list
    Then the password expiration policy is applied to the removed user
    Then the password expiration policy is not applied anymore to the added user

Scenario: Enforcing a password blocking policy
    Given an administrator configuring a Centreon platform and an existing user account not blocked
    When the administrator sets valid password blocking policy and the user attempts to login multiple times
    Then the user is locked after reaching the number of allowed attempts
    Then the user must wait for the defined duration before attempting again