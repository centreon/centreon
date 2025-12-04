import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkPlatformVersion,
  getCentreonPreviousMajorVersion,
  getCentreonStableMinorVersions,
  installCentreon,
  localPackageDirectory
} from '../common';

before(() => {
  if (
    Cypress.env('IS_CLOUD') &&
    (!Cypress.env('INTERNAL_REPO_USERNAME') ||
      !Cypress.env('INTERNAL_REPO_PASSWORD'))
  ) {
    throw new Error(
      'Missing environment variables: INTERNAL_REPO_USERNAME and/or INTERNAL_REPO_PASSWORD required for cloud repository configuration.'
    );
  }

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
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step1.php'
  }).as('getStep1');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step2.php'
  }).as('getStep2');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step3.php'
  }).as('getStep3');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step4.php'
  }).as('getStep4');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step5.php'
  }).as('getStep5');

  cy.intercept({
    method: 'POST',
    url: '/centreon/install/steps/process/generationCache.php'
  }).as('generatingCache');

  cy.intercept('/centreon/api/latest/monitoring/resources*').as(
    'monitoringEndpoint'
  );

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('generateAndReloadPollers');
});

Given(
  'a running platform in major {string} with {string} version',
  function (majorVersionFromExpression: string, versionFromExpression: string) {
    if (
      Cypress.env('IS_CLOUD') &&
      !Cypress.env('WEB_IMAGE_OS').includes('alma')
    ) {
      cy.log('Cloud platforms are only available on almalinux');
      this.skip(); // <-- skip for cloud non-almalinux
    }

    cy.log(`Testing ${Cypress.env('IS_CLOUD') ? 'cloud' : 'onprem'} upgrade`);

    return cy.getWebVersion().then(({ major_version, minor_version }) => {
      cy.task("logVersion", `Current Major version value is ${major_version}`);
      let majorVersionFrom = '0';
      switch (majorVersionFromExpression) {
        case 'n - 1': {
          const previousVersion =
            getCentreonPreviousMajorVersion(major_version);
          cy.log(`Getting Centreon previous major version: ${previousVersion}`);
          cy.task(
            "logVersion",
            `Previous Major version value is ${previousVersion}`,
          );
          // Cloud versioning is different from on-prem
          if (Cypress.env('IS_CLOUD')) {
            const versionDir = './././../../www/install/php';
            const versionFilePath = `${versionDir}/Update-${previousVersion}.${minor_version}.php`;
            cy.task('fileExists', versionFilePath).then((exists) => {
              if (exists) {
                cy.log(`The file with version: ${previousVersion} exist`);
                cy.wrap(previousVersion).as('majorVersionFrom');
                majorVersionFrom = previousVersion;
                cy.task(
                  "logVersion",
                  `Found version value is ${previousVersion}`,
                );
              } else {
                cy.log(
                  `The file with version: ${previousVersion} does not exist`
                );
                cy.getClosestVersionFile(previousVersion, versionDir).then(
                  (versionFilePath) => {
                    cy.log(`The last cloud version is: ${versionFilePath}`);
                    const newVersion = versionFilePath;
                    majorVersionFrom = versionFilePath;
                    cy.wrap(newVersion).as('majorVersionFrom');
                    cy.task(
                      "logVersion",
                      `Closest version found value is ${newVersion}`,
                    );
                  }
                );
              }
            });
          } else {
            majorVersionFrom = previousVersion;
            cy.wrap(previousVersion).as("majorVersionFrom");
            cy.task(
              "logVersion",
              `Found version value is ${previousVersion}`,
            );
          }
          break;
        }
        case 'n - 2':
          majorVersionFrom = getCentreonPreviousMajorVersion(
            getCentreonPreviousMajorVersion(major_version)
          );
          cy.wrap(majorVersionFrom).as('majorVersionFrom');
          break;
        default:
          throw new Error(`${majorVersionFromExpression} not managed.`);
      }

      // Verifying Docker image with task
      cy.get('@majorVersionFrom')
        .then((majorVersionFrom) => {
          const imageName = `docker.centreon.com/centreon/centreon-web-dependencies-${Cypress.env('WEB_IMAGE_OS')}:${majorVersionFrom}`;

          cy.task<{ exists: boolean }>('checkDockerImage', {
            image: imageName,
            name: 'web'
          }).then((result) => {
            if (!result.exists) {
              cy.log(`❌ Docker image ${imageName} not found, skipping test`);
              this.skip(); // skip if Docker image not found
            } else {
              cy.log(`✅ Docker image ${imageName} found, starting container`);
              cy.startContainer({
                command: 'tail -f /dev/null',
                image: imageName,
                name: 'web',
                portBindings: [{ destination: 4000, source: 80 }]
              });
            }
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
                    this.skip(); // <-- skip if version not needed
                  }

                  cy.log(
                    `${versionFromExpression} version is ${stableMinorVersions[minorVersionIndex]}`
                  );
                  const installedVersion = `${majorVersionFrom}.${stableMinorVersions[minorVersionIndex]}`;
                  Cypress.env('installed_version', installedVersion);
                  cy.log('installed_version', installedVersion);
                  cy.task(
                    "logVersion",
                    `Installed version value is ${installedVersion}`,
                  );
                  return installCentreon(installedVersion)
                    .then(() => {
                      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
                        const distrib =
                          Cypress.env('WEB_IMAGE_OS') === 'alma9'
                            ? 'el9'
                            : 'el8';

                        if (Cypress.env('IS_CLOUD')) {
                          cy.log('Configuring cloud internal repository...');

                          const repository = `https://${Cypress.env('INTERNAL_REPO_USERNAME')}:${Cypress.env('INTERNAL_REPO_PASSWORD')}@packages.centreon.com/rpm-standard-internal/${major_version}/${distrib}/centreon-${major_version}-internal.repo`;

                          return cy.execInContainer(
                            {
                              command: [
                                `dnf config-manager --add-repo ${repository}`,
                                `sed -i "s#packages.centreon.com/rpm-standard-internal#${Cypress.env('INTERNAL_REPO_USERNAME')}:${Cypress.env('INTERNAL_REPO_PASSWORD')}@packages.centreon.com/rpm-standard-internal#" /etc/yum.repos.d/centreon-${major_version}-internal.repo`,
                                `dnf config-manager --set-enabled 'centreon*'`
                              ],
                              name: 'web'
                            },
                            { log: false }
                          );
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

                        return cy.execInContainer(
                          {
                            command: `bash -e <<EOF
                          echo "deb https://${Cypress.env('INTERNAL_REPO_USERNAME')}:${Cypress.env('INTERNAL_REPO_PASSWORD')}@packages.centreon.com/apt-standard-internal/ ${Cypress.env('WEB_IMAGE_OS')}-${major_version}-unstable main" > /etc/apt/sources.list.d/centreon-unstable.list
                          apt-get update
EOF`,
                            name: 'web'
                          },
                          { log: false }
                        );
                      }

                      return cy.execInContainer({
                        command: `bash -e <<EOF
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

afterEach(() => {
  cy.visitEmptyPage().stopContainer({ name: 'web' });
});
