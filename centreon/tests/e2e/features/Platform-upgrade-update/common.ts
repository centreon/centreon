/* eslint-disable no-lonely-if */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { CopyToContainerContentType } from '@centreon/js-config/cypress/e2e/commands';

import { checkIfConfigurationIsExported, insertFixture } from '../../commons';

const dateBeforeLogin = new Date();

const localPackageDirectory = 'fixtures/packages';
const containerPackageDirectory = '/tmp/packages-update-centreon';

const getCentreonPreviousMajorVersion = (majorVersionFrom: string): string => {
  const match = majorVersionFrom.match(/^(\d+)\.(\d+)$/);

  if (match === null) {
    throw new Error(`Cannot parse major version ${majorVersionFrom}`);
  }

  let year = match[1];
  let month = match[2];

  if (Number(month) <= 4 || Number(month) > 10) {
    year = (Number(year) - 1).toString();
    month = '10';
  } else {
    month = '04';
  }

  return `${year}.${month}`;
};

const getCentreonStableMinorVersions = (
  majorVersion: string
): Cypress.Chainable => {
  cy.log(`Getting Centreon stable versions of ${majorVersion}...`);

  let commandResult;
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    commandResult = cy
      .execInContainer({
        command: `dnf config-manager --set-disabled 'centreon-*-unstable*' 'centreon-*-testing*'`,
        name: 'web'
      })
      .execInContainer({
        command: `dnf --showduplicates list centreon-web | grep centreon-web | grep '${majorVersion}' | awk '{ print $2 }' | tr '\n' ' '`,
        name: 'web'
      });
  } else {
    commandResult = cy
      .execInContainer({
        command: [
          `mv /etc/apt/sources.list.d/centreon-unstable.list /etc/apt/sources.list.d/centreon-unstable.list.bak`,
          `mv /etc/apt/sources.list.d/centreon-testing.list /etc/apt/sources.list.d/centreon-testing.list.bak`,
          `apt-get update`
        ],
        name: 'web'
      })
      .execInContainer({
        command: `apt list -a centreon-web | grep '${majorVersion}' | awk '{ print $2 }'`,
        name: 'web'
      });
  }

  return commandResult.then(({ output }): Cypress.Chainable<Array<number>> => {
    const stableVersions: Array<number> = [];

    const versionsRegex = /\d+\.\d+\.(\d+)/g;

    [...output.matchAll(versionsRegex)].forEach((result) => {
      cy.log(`available version found: ${majorVersion}.${result[1]}`);
      stableVersions.push(Number(result[1]));
    });

    if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
      cy.execInContainer({
        command: "dnf config-manager --set-enabled 'centreon-*'",
        name: 'web'
      });
    } else {
      cy.execInContainer({
        command: [
          `mv /etc/apt/sources.list.d/centreon-unstable.list.bak /etc/apt/sources.list.d/centreon-unstable.list`,
          `mv /etc/apt/sources.list.d/centreon-testing.list.bak /etc/apt/sources.list.d/centreon-testing.list`,
          `apt-get update`
        ],
        name: 'web'
      });
    }
    const lastStableMinorVersion = [...new Set(stableVersions)]
      .sort((a, b) => a - b)
      .pop();
    cy.log('lastStableMinorVersion', lastStableMinorVersion);
    Cypress.env('lastStableMinorVersion', lastStableMinorVersion);

    return cy.wrap([...new Set(stableVersions)].sort((a, b) => a - b)); // remove duplicates and order
  });
};

const installDatabase = (): void => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    const osMatches = Cypress.env('WEB_IMAGE_OS').match(/alma(\d+)/);
    cy.execInContainer({
      command: [
        `bash -e <<EOF
          curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --os-type=rhel --skip-check-installed --skip-maxscale --os-version=${osMatches[1]} --mariadb-server-version="mariadb-10.5"
EOF`,
        `dnf install -y mariadb-server mariadb`,
      ],
      name: 'web'
    });
  } else {
    let osType = 'debian';
    let osVersion = '12';
    if (Cypress.env('WEB_IMAGE_OS') === 'jammy') {
      osType = 'ubuntu';
      osVersion = 'jammy';
    }
    cy.execInContainer({
      command: [
        `bash -e <<EOF
          curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --os-type=${osType} --skip-check-installed --skip-maxscale --os-version=${osVersion} --mariadb-server-version="mariadb-10.11"
EOF`,
        `apt-get update`,
        `apt-get install -y mariadb-server mariadb-client`,
      ],
      name: 'web'
    });
  }

};

const installCentreon = (version: string): Cypress.Chainable => {
  cy.log(`installing version ${version}...`);

  const versionMatches = version.match(/(\d+)\.(\d+)\.\d+/);
  if (!versionMatches) {
    throw new Error('Cannot parse version number.');
  }

  cy.execInContainer({
    command: [
      `mkdir -p /usr/lib/centreon/plugins`,
      `chmod 0755 /usr/lib/centreon/plugins`,
    ],
    name: 'web'
  });

  if (Number(versionMatches[1]) > 24 || (Number(versionMatches[1]) === 24 && Number(versionMatches[2]) >= 10)) {
    // database is not installed in dependencies containers > 24.10
    installDatabase();
  }

  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    cy.execInContainer({
      command: [
        `dnf config-manager --set-disabled 'centreon-*-unstable*' 'centreon-*-testing*'`,
        `dnf install -y centreon-web-${version}`,
        `dnf install -y centreon-broker-cbd`,
        `echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini`,
        `/etc/init.d/mysql start`,
        `mkdir -p /run/php-fpm`,
        `systemctl restart php-fpm`,
        `systemctl restart httpd`,
        `mysql -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY 'centreon' WITH GRANT OPTION"`,
        `dnf config-manager --set-enabled 'centreon-*'`
      ],
      name: 'web'
    });
  } else {
    let packageDistribPrefix;
    let packageDistribName;
    if (Number(versionMatches[1]) < 24) {
      packageDistribPrefix = '-';
      packageDistribName = Cypress.env('WEB_IMAGE_OS');
    } else if (Number(versionMatches[1]) === 24 && Number(versionMatches[2]) < 10) {
      packageDistribPrefix = '-1~';
      packageDistribName = Cypress.env('WEB_IMAGE_OS');
    } else if (Cypress.env('WEB_IMAGE_OS') === 'bookworm') {
      packageDistribPrefix = '-*+';
      packageDistribName = 'deb12u1';
    } else if (Cypress.env('WEB_IMAGE_OS') === 'jammy') {
      packageDistribPrefix = '-*-';
      packageDistribName = '0ubuntu.22.04';
    } else {
      throw new Error(`Distrib ${Cypress.env('WEB_IMAGE_OS')} not managed in update/upgrade tests.`);
    }

    const packageVersionSuffix = `${version}${packageDistribPrefix}${packageDistribName}`;
    const packagesToInstall = [
      `centreon-poller='${packageVersionSuffix}'`,
      `centreon-web='${packageVersionSuffix}'`,
      `centreon-trap='${packageVersionSuffix}'`,
      `centreon-perl-libs='${packageVersionSuffix}'`
    ];
    if (Number(versionMatches[1]) < 24) {
      packagesToInstall.push(`centreon-web-apache=${packageVersionSuffix}`);
    }
    const phpVersion = Number(versionMatches[1]) <= 24 && Number(versionMatches[2]) < 10 ? '8.1' : '8.2';

    cy.execInContainer({
      command: [
        `mv /etc/apt/sources.list.d/centreon-unstable.list /etc/apt/sources.list.d/centreon-unstable.list.bak`,
        `mv /etc/apt/sources.list.d/centreon-testing.list /etc/apt/sources.list.d/centreon-testing.list.bak`,
        `apt-get update`,
        `apt-get install -y ${packagesToInstall.join(' ')}`,
        `mkdir -p /usr/lib/centreon-connector`,
        `echo "date.timezone = Europe/Paris" > /etc/php/${phpVersion}/mods-available/timezone.ini`,
        `phpenmod -v ${phpVersion} timezone`,
        `sed -i 's#^datadir_set=#datadir_set=1#' /etc/init.d/mysql`,
        `service mysql start`,
        `mkdir -p /run/php`,
        `systemctl restart php${phpVersion}-fpm`,
        `systemctl restart apache2`,
        `mysql -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY 'centreon' WITH GRANT OPTION"`,
        `mv /etc/apt/sources.list.d/centreon-unstable.list.bak /etc/apt/sources.list.d/centreon-unstable.list`,
        `mv /etc/apt/sources.list.d/centreon-testing.list.bak /etc/apt/sources.list.d/centreon-testing.list`,
        `apt-get update`,
        `usermod -a -G centreon-broker www-data` // temporary fix (MON-20769)
      ],
      name: 'web'
    });
  }

  cy.log(`Version ${version} installed on filesystem`);

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/steps/step.php?action=nextStep'
  }).as('nextStep');

  cy.intercept({
    method: 'POST',
    url: '/centreon/install/steps/process/generationCache.php'
  }).as('cacheGeneration');

  // Step 1
  cy.visit('/centreon/install/install.php')
    .get('th.step-wrapper span')
    .contains(1);
  cy.get('#next').click();

  // Step 2
  cy.get('th.step-wrapper span').contains(2);
  cy.wait('@nextStep').get('#next').click();

  // Step 3
  cy.get('th.step-wrapper span').contains(3);
  cy.wait('@nextStep').get('#next').click();

  // Step 4
  cy.get('th.step-wrapper span').contains(4);
  cy.wait('@nextStep').get('#next').click();

  // Step 5
  cy.get('th.step-wrapper span').contains(5);
  cy.get('input[name="admin_password"]').type(
    '{selectall}{backspace}Centreon!2021'
  );
  cy.get('input[name="confirm_password"]').type(
    '{selectall}{backspace}Centreon!2021'
  );
  cy.get('input[name="firstname"]').type('{selectall}{backspace}centreon');
  cy.get('input[name="lastname"]').type('{selectall}{backspace}centreon');
  cy.get('input[name="email"]').type(
    '{selectall}{backspace}centreon@localhost'
  );
  cy.wait('@nextStep').get('#next').click();

  // Step 6
  cy.get('th.step-wrapper span').contains(6);
  cy.get('input[name="root_password"]').type('{selectall}{backspace}centreon');
  cy.get('input[name="db_password"]').type('{selectall}{backspace}centreon');
  cy.get('input[name="db_password_confirm"]').type(
    '{selectall}{backspace}centreon'
  );
  cy.wait('@nextStep').get('#next').click();

  // Step 7
  cy.get('th.step-wrapper span').contains(7);
  cy.wait('@cacheGeneration', { timeout: 60000 })
    .get('tbody#step_contents span:contains("OK")')
    .should('have.length', 7);
  cy.wait('@nextStep').get('#next').click();

  // Step 8
  cy.get('th.step-wrapper span').contains(8);
  cy.wait('@nextStep').get('#next').click();

  // Step 9
  cy.get('th.step-wrapper span').contains(9);
  cy.wait('@nextStep');
  cy.get('#send_statistics').uncheck({ force: true });
  cy.get('#finish').click();

  cy.log(`Version ${version} installed using web wizard`);

  return cy
    .setUserTokenApiV1()
    .applyPollerConfiguration()
    .execInContainer({
      command: [
        `systemctl restart cbd`,
        `systemctl restart centengine`,
        `systemctl restart gorgoned`
      ],
      name: 'web'
    });
};

const updatePlatformPackages = (): Cypress.Chainable => {
  return cy
    .copyToContainer({
      destination: containerPackageDirectory,
      source: `./${localPackageDirectory}`,
      type: CopyToContainerContentType.Directory
    })
    .getWebVersion()
    .then(({ major_version }) => {
      let installCommands: Array<string> = [];

      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
        if ([Cypress.env('STABILITY'), Cypress.env('TARGET_STABILITY')].includes('testing')) {
          installCommands = [
            ...installCommands,
            `dnf config-manager --set-disabled 'centreon*unstable*'`
          ];
        } else if (Cypress.env('STABILITY') === 'stable') {
          installCommands = [
            ...installCommands,
            `dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing*'`
          ];
        }
      } else {
        if ([Cypress.env('STABILITY'), Cypress.env('TARGET_STABILITY')].includes('testing')) {
          installCommands = [
            ...installCommands,
            `rm -f /etc/apt/sources.list.d/centreon*unstable*`
          ];
        } else if (Cypress.env('STABILITY') === 'stable') {
          installCommands = [
            ...installCommands,
            `rm -f /etc/apt/sources.list.d/centreon*{unstable,testing}*`
          ];
        }
      }

      switch (Cypress.env('WEB_IMAGE_OS')) {
        case 'alma8':
          installCommands = [
            ...installCommands,
            `rm -f ${containerPackageDirectory}/centreon{,-central,-mariadb,-mysql}-${major_version}*.rpm`,
            `dnf module reset -y php`,
            `dnf module install -y php:remi-8.2`,
            `dnf install -y ${containerPackageDirectory}/*.rpm`
          ];
          break;
        case 'alma9':
          installCommands = [
            ...installCommands,
            `rm -f ${containerPackageDirectory}/centreon{,-central,-mariadb,-mysql}-${major_version}*.rpm`,
            `dnf module reset -y php`,
            `dnf module enable -y php:8.2`,
            `dnf install -y ${containerPackageDirectory}/*.rpm`
          ];
          break;
        default:
          installCommands = [
            ...installCommands,
            `rm -f ${containerPackageDirectory}/centreon{,-central,-mariadb,-mysql}_${major_version}*.deb`,
            `apt-get update`,
            `apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y ${containerPackageDirectory}/centreon-*.deb`
          ];
      }

      return cy.execInContainer({
        command: installCommands,
        name: 'web'
      });
    })
    .execInContainer({
      command: [
        'systemctl restart cbd',
        'systemctl restart centengine',
        'systemctl restart gorgoned'
      ],
      name: 'web'
    });
};

const checkPlatformVersion = (platformVersion: string): Cypress.Chainable => {
  const command = Cypress.env('WEB_IMAGE_OS').includes('alma')
    ? `rpm -qa | grep centreon-web | cut -d '-' -f3 | tr -d '\n'`
    : `apt list --installed centreon-web | awk '{ print $2 }' | cut -d '-' -f1 | tr -d '\n'`;

  return cy
    .execInContainer({
      command,
      name: 'web'
    })
    .then(({ output }): Cypress.Chainable<null> | null => {
      const isExpected = platformVersion === output;
      if (isExpected) {
        return null;
      }

      throw new Error(
        `The platform version is not the correct one (expected: ${platformVersion}, actual: ${output}).`
      );
    });
};

const insertResources = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json',
    'resources/clapi/service2/01-add.json',
    'resources/clapi/service2/02-set-max-check.json',
    'resources/clapi/service2/03-disable-active-check.json',
    'resources/clapi/service2/04-enable-passive-check.json',
    'resources/clapi/service3/01-add.json',
    'resources/clapi/service3/02-set-max-check.json',
    'resources/clapi/service3/03-disable-active-check.json',
    'resources/clapi/service3/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const prepareUpdateFileForUpgrade = (): Cypress.Chainable => {
  return cy.getWebVersion().then(({ major_version, minor_version }) => {
    const targetUpdateFile = `/usr/share/centreon/www/install/php/Update-${major_version}.${minor_version}.php`;

    // Check if the version-specific file already exists
    return cy.execInContainer({
      command: `ls ${targetUpdateFile} || echo "File not found"`,
      name: 'web'
    }).then((fileCheckResult) => {
      // If version-specific file already exists, no action needed
      if (!fileCheckResult.output.includes("File not found")) {
        cy.log(`Version-specific update file already exists in container: ${targetUpdateFile}`);
        return cy.wrap(null);
      }

      // If version-specific file does not exist => copy content from Update-next.php
      return cy.exec(`ls ../../www/install/php/Update-next.php || echo ""`)
      .then((result) => {
        const updateNextFile = result.stdout.trim();
        if (!updateNextFile) {
          cy.log("Update-next.php file not found");
          return cy.wrap(null);
        }

        // Copy the Update-next.php content to container with proper name
        return cy.copyToContainer({
          source: updateNextFile,
          destination: targetUpdateFile,
          type: CopyToContainerContentType.File
        })
        .then(() => {
          // Check if file was copied successfully
          return cy.execInContainer({
            command: `ls -la ${targetUpdateFile} || echo "File not found after copy"`,
            name: 'web'
          }).then((lsResult) => {

            if (lsResult.output.includes("File not found")) {
              cy.log("WARNING: Copy operation did not create the target file");
              return cy.wrap(null);
            }

            // Change version in the file
            return cy.execInContainer({
              command: `sed -i "s/version = '';/version = '${major_version}.${minor_version}';/g" ${targetUpdateFile}`,
              name: 'web'
            });
          });
        });
      });
    });
  });
};

When('administrator updates packages to current version', () => {
  updatePlatformPackages();
});

When('administrator runs the update procedure', () => {
  prepareUpdateFileForUpgrade()
  .then(() => {
    cy.visit('/');

    cy.wait('@getStep1', { timeout: 60000 }).then(() => {
      cy.get('.btc.bt_info').should('be.visible').click();
    });

    cy.wait('@getStep2').then(() => {
      cy.get('span[style]').each(($span) => {
        cy.wrap($span).should('have.text', 'Loaded');
      });
      cy.get('.btc.bt_info').should('be.visible').click();
    });

    cy.wait('@getStep3');
    cy.contains('Release notes');
    // check correct updated version
    const installed_version = Cypress.env('installed_version');
    cy.log(`installed_version : ${installed_version}`);

    if (['testing', 'stable'].includes(Cypress.env('STABILITY'))) {
      cy.getWebVersion().then(({ major_version, minor_version }) => {
        cy.contains(
          `upgraded from version ${installed_version} to ${major_version}.${minor_version}`
        ).should('be.visible');
      });
    }

    // button is disabled during 3s in order to read documentation
    cy.get('#next', { timeout: 15000 }).should('be.enabled').click();

    cy.wait('@generatingCache')
      .get('span[style]', { timeout: 15000 })
      .each(($span) => {
        cy.wrap($span).should('have.text', 'OK');
      });
    cy.get('.btc.bt_info', { timeout: 15000 }).should('be.visible').click();

    cy.wait('@getStep5');
    cy.contains('Congratulations');

    // disable statistics if checkbox is available (only on upgrade to new major version)
    cy.get('body').then(($body) => {
      if ($body.find('#send_statistics').length) {
        cy.get('#send_statistics').uncheck({ force: true });
      }
    });

    cy.get('.btc.bt_success').should('be.visible').click();
  });
});

Then(
  'monitoring should be up and running after update procedure is complete to current version',
  () => {
    cy.setUserTokenApiV1();

    cy.addTimePeriod({
      name: '24/7'
    })
      .addCheckCommand({
        command: 'echo "failure" && exit 2',
        enableShell: true,
        name: 'check_command'
      })
      .addHost({
        checkCommand: 'check_command',
        name: 'host1'
      })
      .addServiceTemplate({
        name: 'serviceTemplate1'
      })
      .addService({
        checkCommand: 'check_command',
        host: 'host1',
        name: 'service1',
        template: 'serviceTemplate1'
      })
      .applyPollerConfiguration();
    cy.visit('/');
    if (['testing', 'stable'].includes(Cypress.env('STABILITY'))) {
      cy.getWebVersion().then(({ major_version, minor_version }) => {
        cy.contains(`${major_version}.${minor_version}`).should('be.visible');
      });
    }
    cy.loginByTypeOfUser({
      jsonName: 'admin'
    }).wait('@getLastestUserFilters');

    cy.url().should('include', '/monitoring/resources');

    cy.get('[aria-label="State filter"]').click();
    cy.get('[data-value="all"]').click();

    cy.waitUntil(
      () => {
        cy.get('[aria-label="Refresh"]').click({ force: true });

        return cy.get('#content').then(($el) => {
          return $el.find(':contains("service1")').length > 0;
        });
      },
      {
        timeout: 20000
      }
    );
  }
);

Then('legacy services grid page should still work', () => {
  cy.visit('/centreon/main.php?p=20204&o=svcOV_pb').wait('@getTimeZone');

  cy.waitUntil(() => {
    cy.get('iframe#main-content')
      .its('0.contentDocument.body')
      .find('select#typeDisplay2')
      .select('All');

    cy.getIframeBody().find('a#JS_monitoring_refresh').click({ force: true });

    return cy
      .getIframeBody()
      .find('.ListTable tr:not(.ListHeader)')
      .then(($el) => {
        return $el.find(':contains("host1")').length > 0;
      });
  });
});

Given('a successfully updated platform', () => {
  cy.setUserTokenApiV1();

  cy.loginByTypeOfUser({
    jsonName: 'admin'
  });
});

When('administrator exports Poller configuration', () => {
  cy.addHost({
    checkCommand: 'check_command',
    name: 'host2'
  });

  cy.exportConfig();
  cy.wait('@generateAndReloadPollers').then(() => {
    cy.contains('Configuration exported and reloaded').should('have.length', 1);
  });
});

Then('Poller configuration should be fully generated', () => {
  checkIfConfigurationIsExported({ dateBeforeLogin, hostName: 'host2' });
});

export {
  localPackageDirectory,
  containerPackageDirectory,
  getCentreonPreviousMajorVersion,
  getCentreonStableMinorVersions,
  installCentreon,
  updatePlatformPackages,
  checkPlatformVersion,
  dateBeforeLogin,
  insertResources
};
