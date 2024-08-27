import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
});

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('userTimeZone');
});

Given('an admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the admin user acces to the backup page', () => {
  cy.navigateTo({
    page: 'Backup',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  });
  cy.wait('@userTimeZone');
});

Then('backup is enable in UI', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('input[name="backup_enabled[backup_enabled]"]').should('be.checked');
  });
});

Then('backup directory is set', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('input[name="backup_backup_directory"]').should(
      'have.value',
      '/var/cache/centreon/backup'
    );
  });
});

Then('backup temporary is set', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('input[name="backup_tmp_directory"]').should(
      'have.value',
      '/tmp/backup'
    );
  });
});

Then('database backup options is set', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('input[name="backup_database_centreon"]').should('be.checked');
  });
});

Then('Mysql configuration file path is set', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('input[name="backup_retention"]').should('have.value', '7');
    cy.get('input[name="backup_configuration_files"]').should('be.checked');
    cy.get('input[name="backup_mysql_conf"]').should(
      'have.value',
      '/etc/my.cnf.d/centreon.cnf'
    );
    cy.get(
      'input[name="backup_export_scp_enabled[backup_export_scp_enabled]"]'
    ).should('be.checked');
  });
});

Then('the admin user enables backup for all configuration files', () => {
  cy.enterIframe('#main-content').within(() => {
    cy.get('label')
      .contains('Yes')
      .prev('input[type="radio"]')
      .check({ force: true });
    cy.get('label')
      .contains('Dump')
      .prev('input[type="radio"]')
      .check({ force: true });
  });
});

Then('the admin user selects full backup day', () => {
  cy.selectCurrentDayCheckbox();
});

Then(
  'the admin user saves the backup configuration and export the configuration',
  () => {
    cy.enterIframe('#main-content').within(() => {
      cy.get('input[name="backup_mysql_conf"]')
        .clear()
        .type('/etc/my.cnf.d/container.cnf');
      cy.get('input[name="submitC"]').click();
    });
    cy.exportConfig();
  }
);

Then('after the scheduled cron job has run', () => {
  cy.execInContainer({
    command: `/usr/share/centreon/cron/centreon-backup.pl`,
    name: 'web'
  });
});

Then(
  'the database backups and configuration files should be present in the backup directory',
  () => {
    cy.execInContainer({
      command: 'sh -c "cd /var/cache/centreon/backup && ls"',
      name: 'web'
    }).then((lsResult) => {
      cy.log(lsResult.stdout);
      const todayDate = new Date().toISOString().split('T')[0];
      const expectedFiles = [
        `${todayDate}-centreon_storage.sql.gz`,
        `${todayDate}-centreon.sql.gz`
      ];

      expectedFiles.forEach((file) => {
        expect(lsResult.stdout).to.contain(file);
      });
    });
  }
);
