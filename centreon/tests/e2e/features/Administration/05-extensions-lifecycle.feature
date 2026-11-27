Feature: Extensions lifecycle
  As a Centreon administrator
  I want to install, update and delete an extension from the Extensions manager
  So that the platform keeps working whatever the extension lifecycle

  @MON-214336 @system
  Scenario: An extension is installed, updated and deleted from the Extensions manager and the platform keeps working
    Given an admin user is logged in
    And a disposable module "rbt-extension-module" in version "1.0.0" is present on the platform and not installed
    When the admin installs "rbt-extension-module" from the Extensions manager
    Then the module is listed as installed in version "1.0.0"
    When the module files are upgraded to version "1.0.1"
    And the admin updates "rbt-extension-module" from the Extensions manager
    Then the module is listed as up to date in version "1.0.1"
    When the admin deletes "rbt-extension-module" from the Extensions manager
    Then the module is listed as not installed
    # "Live Top 10 Memory Usage" is a core widget (no <version> in its configs.xml):
    # the Extensions manager offers no delete action for it and the backend only
    # removes non-internal widgets, so it cannot be deleted and installed again.
    When the admin opens the widget "Live Top 10 Memory Usage" in the Extensions manager
    Then the widget is installed once
    And the widget offers no delete action
    And no module or menu entry is duplicated
    And the Resources Status and Hosts pages still open without error
