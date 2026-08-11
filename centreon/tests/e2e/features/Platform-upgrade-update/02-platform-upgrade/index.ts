import { Given } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkPlatformVersion,
  getCentreonPreviousMajorVersion,
  getCentreonStableMinorVersions,
  installCentreon,
  localPackageDirectory
} from '../common';

before(() => {
  if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
    cy.exec(`ls ${localPackageDirectory}/centreon-web-*.rpm`);
  } else {
    cy.exec(`ls ${localPackageDirectory}/centreon-web_*.deb`);
  }
});

beforeEach(() => {
  // clear network cache to avoid chunk loading issues
  cy.wrap(
    Cypress.automation('remote:debugger:protocol', {
      command: 'Network.clearBrowserCache'
    })
  );

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');

  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.events_view_users}?page=1&limit=100`
  }).as('getLastestUserFilters');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.step1_upgrade
  }).as('getStep1');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.step2_upgrade
  }).as('getStep2');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.step3_upgrade
  }).as('getStep3');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.step4_upgrade
  }).as('getStep4');

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.step5_upgrade
  }).as('getStep5');

  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.pages.generation_cache
  }).as('generatingCache');

  cy.intercept(`${INTERCEPTORS.api.monitor_resources}*`).as(
    'monitoringEndpoint'
  );

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.generate_reload_pollers
  }).as('generateAndReloadPollers');
});

Given(
  'a running platform in major {string} with {string} version',
  (majorVersionFromExpression: string, versionFromExpression: string) => {
    if (
      Cypress.env('IS_CLOUD') &&
      !Cypress.env('WEB_IMAGE_OS').includes('alma')
    ) {
      cy.log('Cloud platforms are only available on almalinux');

      return cy.wrap('skipped');
    }

    cy.log(`Testing ${Cypress.env('IS_CLOUD') ? 'cloud' : 'onprem'} upgrade`);

    return cy.getWebVersion().then(({ major_version, minor_version }) => {
      cy.task('logVersion', `Current Major version value is ${major_version}`);
      let majorVersionFrom = '0';
      switch (majorVersionFromExpression) {
        case 'n - 1': {
          const previousVersion =
            getCentreonPreviousMajorVersion(major_version);
          cy.log(`Getting Centreon previous major version: ${previousVersion}`);
          cy.task(
            'logVersion',
            `Previous Major version value is ${previousVersion}`
          );
          // Cloud versioning is different from on-prem
          if (Cypress.env('IS_CLOUD')) {
            const versionDir = './././../../www/install/php';
            // Check if a file with the major version exists
            const versionFilePath = `${versionDir}/Update-${previousVersion}.${minor_version}.php`;
            cy.task('fileExists', versionFilePath).then((exists) => {
              if (exists) {
                cy.log(`The file with version: ${previousVersion} exist`);
                cy.wrap(previousVersion).as('majorVersionFrom');
                majorVersionFrom = previousVersion;
                cy.task(
                  'logVersion',
                  `Found version value is ${previousVersion}`
                );
              } else {
                cy.log(
                  `The file with version: ${previousVersion} does not exist`
                );
                // If the version isn't found, use the closest available one
                cy.getClosestVersionFile(previousVersion, versionDir).then(
                  (versionFilePath) => {
                    cy.log(`The last cloud version is: ${versionFilePath}`);
                    const newVersion = versionFilePath;
                    majorVersionFrom = versionFilePath;
                    cy.wrap(newVersion).as('majorVersionFrom');
                    cy.task(
                      'logVersion',
                      `Closest version found value is ${newVersion}`
                    );
                  }
                );
              }
            });
          } else {
            majorVersionFrom = previousVersion;
            cy.wrap(previousVersion).as('majorVersionFrom');
            cy.task('logVersion', `Found version value is ${previousVersion}`);
          }
          break;
        }
        case 'n - 2':
          majorVersionFrom = getCentreonPreviousMajorVersion(
            getCentreonPreviousMajorVersion(major_version)
          );
          break;
        default:
          throw new Error(`${majorVersionFromExpression} not managed.`);
      }

      cy.get('@majorVersionFrom')
        .then((majorVersionFrom) => {
          cy.startContainer({
            command: 'tail -f /dev/null',
            image: `docker.centreon.com/centreon/centreon-web-dependencies-${Cypress.env(
              'WEB_IMAGE_OS'
            )}:${majorVersionFrom}`,
            name: 'web',
            portBindings: [
              {
                destination: 4000,
                source: 80
              }
            ]
          });
        })
        .then(() => {
          Cypress.config('baseUrl', 'http://127.0.0.1:4000');

          return cy
            .intercept('/waiting-page', {
              headers: { 'content-type': 'text/html' },
              statusCode: 200
            })
            .visit('/waiting-page')
            .then(() => {
              return getCentreonStableMinorVersions(majorVersionFrom).then(
                (stableMinorVersions) => {
                  let minorVersionIndex = 0;
                  switch (versionFromExpression) {
                    case 'last stable':
                      minorVersionIndex = stableMinorVersions.length - 1;
                      break;
                    case 'last stable - 1':
                      minorVersionIndex = stableMinorVersions.length - 2;
                      break;
                    default:
                      throw new Error(`${versionFromExpression} not managed.`);
                  }
                  if (
                    minorVersionIndex < 0 ||
                    (majorVersionFrom === major_version &&
                      minorVersionIndex === 0)
                  ) {
                    cy.log(
                      `Not needed to test ${versionFromExpression} version.`
                    );

                    return cy.stopContainer({ name: 'web' }).wrap('skipped');
                  }

                  cy.log(
                    `${versionFromExpression} version is ${stableMinorVersions[minorVersionIndex]}`
                  );
                  const installedVersion = `${majorVersionFrom}.${stableMinorVersions[minorVersionIndex]}`;
                  Cypress.env('installed_version', installedVersion);
                  cy.log('installed_version', installedVersion);
                  cy.task(
                    'logVersion',
                    `Installed version value is ${installedVersion}`
                  );
                  return installCentreon(installedVersion)
                    .then(() => {
                      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
                        const distrib = `el${Cypress.env('WEB_IMAGE_OS').replace('alma', '')}`;

                        if (Cypress.env('IS_CLOUD')) {
                          cy.log('Configuring cloud internal repository...');

                          return cy.execInContainer({
                            command: [
                              `dnf config-manager --add-repo https://packages.centreon.com/rpm-standard-internal/${major_version}/${distrib}/centreon-${major_version}-internal.repo`,
                              `dnf config-manager --set-enabled 'centreon*'`
                            ],
                            name: 'web'
                          });
                        }

                        return cy.execInContainer({
                          command: [
                            `dnf config-manager --add-repo https://packages.centreon.com/rpm-standard/${major_version}/${distrib}/centreon-${major_version}.repo`,
                            `dnf config-manager --set-enabled 'centreon*'`
                          ],
                          name: 'web'
                        });
                      }

                      cy.execInContainer({
                        command: `bash -e <<EOF
                          echo "deb https://packages.centreon.com/apt-plugins-stable/ ${Cypress.env('WEB_IMAGE_OS')} main" > /etc/apt/sources.list.d/centreon-plugins-stable.list
                          echo "deb https://packages.centreon.com/apt-plugins-testing/ ${Cypress.env('WEB_IMAGE_OS')} main" > /etc/apt/sources.list.d/centreon-plugins-testing.list
                          echo "deb https://packages.centreon.com/apt-plugins-unstable/ ${Cypress.env('WEB_IMAGE_OS')} main" > /etc/apt/sources.list.d/centreon-plugins-unstable.list
                          wget -O- https://packages.centreon.com/api/security/keypair/APT-GPG-KEY/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1
EOF`,
                        name: 'web'
                      });

                      if (Cypress.env('IS_CLOUD')) {
                        cy.log('Configuring cloud internal repository...');

                        return cy.execInContainer({
                          command: `bash -e <<EOF
                          echo "deb https://packages.centreon.com/apt-standard-internal/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-unstable main" > /etc/apt/sources.list.d/centreon-unstable.list
                          apt-get update
EOF`,
                          name: 'web'
                        });
                      }

                      return cy.execInContainer({
                        command: `bash -e <<EOF
                        echo "deb https://packages.centreon.com/apt-standard/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-stable main" > /etc/apt/sources.list.d/centreon-stable.list
                        echo "deb https://packages.centreon.com/apt-standard/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-testing-hotfix main" > /etc/apt/sources.list.d/centreon-testing-hotfix.list
                        echo "deb https://packages.centreon.com/apt-standard/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-testing-release main" > /etc/apt/sources.list.d/centreon-testing-release.list
                        echo "deb https://packages.centreon.com/apt-standard/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-unstable main" > /etc/apt/sources.list.d/centreon-unstable.list
                        apt-get update
EOF`,
                        name: 'web'
                      });
                    })
                    .then(() => {
                      return checkPlatformVersion(
                        `${majorVersionFrom}.${stableMinorVersions[minorVersionIndex]}`
                      ).then(() => cy.visit('/'));
                    });
                }
              );
            });
        });
    });
  }
);

Given(
  'the central broker is {string} to the cbd daemon',
  (brokerLink: string) => {
    // HA platforms default to the central broker NOT linked to the cbd daemon
    // (daemon = 0). The upgrade must still locate the broker configuration.
    if (brokerLink !== 'linked' && brokerLink !== 'not linked') {
      throw new Error(`Unsupported broker_link value: ${brokerLink}`);
    }
    const daemon = brokerLink === 'linked' ? '1' : '0';

    cy.requestOnDatabase({
      database: 'centreon',
      query: `UPDATE cfg_centreonbroker SET daemon = '${daemon}' WHERE config_name = 'central-broker-master'`
    });
  }
);

afterEach(() => {
  cy.visitEmptyPage().stopContainer({ name: 'web' });
});
