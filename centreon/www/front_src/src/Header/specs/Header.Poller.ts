import { renderHook, act } from '@testing-library/react';
import { useAtomValue } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import {
  labelPollers,
  labelNoLatencyDetected,
  labelLatencyDetected,
  labelAllPollers,
  labelDatabaseUpdateAndActive,
  labelDatabaseUpdatesNotActive,
  labelPollerNotRunning,
  labelDatabaseNotActive,
  labelConfigurePollers,
  labelExportConfiguration,
  labelExportAndReloadTheConfiguration,
  labelCancel,
  labelExportAndReload,
  labelExportingAndReloadingTheConfiguration,
  labelConfigurationExportedAndReloaded
} from '../Poller/translatedLabels';
import { pollerConfigurationPageNumber } from '../Poller/getPollerPropsAdapter';
import useNavigation from '../../Navigation/useNavigation';

import { initialize } from './Header.testUtils';

const getElements = (): void => {
  cy.findByRole('button', { name: labelPollers, timeout: 5000 }).as(
    'pollerButton'
  );

  cy.findByRole('status', { name: 'database' }).as('databaseIndicator');
  cy.findByRole('status', { name: 'latency' }).as('latencyIndicator');
};

const submenuShouldBeClosed = (label): void => {
  cy.findByRole('button', { name: label })
    .as('button')
    .should('have.attr', 'aria-expanded', 'false');

  cy.get('@button').within(() => {
    cy.findByTestId('ExpandLessIcon').should('be.visible');
  });
  cy.get(`#${label}-menu`).should('not.be.visible').should('exist');
};

const openSubMenu = (label: string): void => {
  cy.findByRole('button', {
    name: label
  }).click();
  submenuShouldBeOpened(label);
};

const submenuShouldBeOpened = (label: string): void => {
  cy.findByRole('button', { name: label })
    .as('button')
    .should('have.attr', 'aria-expanded', 'true');

  cy.get('@button').within(() => {
    cy.findByTestId('ExpandMoreIcon').should('be.visible');
  });
  cy.get(`#${label}-menu`).should('be.visible').should('exist');
};

export default (): void =>
  describe('Pollers', () => {
    describe('responsive behaviors', () => {
      it('should hide button text at smaller screen size', () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@pollerButton').within(() => {
          cy.findByText('Pollers').should('be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DeviceHubIcon').should('be.visible');
        });

        cy.viewport(767, 300);
        cy.get('@pollerButton').within(() => {
          cy.findByText('Pollers').should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DeviceHubIcon').should('be.visible');
        });
      });

      it('should hide top counters at very small size', () => {
        initialize();
        getElements();

        cy.viewport(599, 300);
        cy.get('@databaseIndicator').should('not.be.visible');
        cy.get('@latencyIndicator').should('not.be.visible');
      });
    });

    describe('top status indicators', () => {
      describe('database', () => {
        it('should alert database critical issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                database: {
                  critical: {
                    total: 1
                  }
                }
              }
            }
          });
          getElements();

          cy.get('@databaseIndicator').should(
            'contain.text',
            labelDatabaseNotActive
          );
        });

        it('should validate database with no issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                database: {
                  critical: {
                    total: 0
                  },
                  warning: {
                    total: 0
                  }
                }
              }
            }
          });
          getElements();

          cy.get('@databaseIndicator').should(
            'contain.text',
            labelDatabaseUpdateAndActive
          );
        });
      });

      describe('latency', () => {
        it('should alert latency critical issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                latency: {
                  critical: {
                    total: 1
                  }
                }
              }
            }
          });
          getElements();

          cy.get('@latencyIndicator').should(
            'contain.text',
            labelLatencyDetected
          );
        });

        it('should validate latency with no issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                latency: {
                  critical: {
                    total: 0
                  },
                  warning: {
                    total: 0
                  }
                }
              }
            }
          });
          getElements();

          cy.get('@latencyIndicator').should(
            'contain.text',
            labelNoLatencyDetected
          );
        });
      });
    });

    describe('sub menu', () => {
      it('should have a button to open the submenu', () => {
        initialize();
        getElements();
        submenuShouldBeClosed('Pollers');
        cy.get('@pollerButton').should('be.visible');
        cy.get('@pollerButton').click();
        submenuShouldBeOpened('Pollers');
        cy.matchImageSnapshot();
      });

      it('should be able to close the submenu by clicking outside, using esc key, or clicking again on the button', () => {
        initialize();
        getElements();

        openSubMenu('Pollers');

        cy.get('body').type('{esc}');
        submenuShouldBeClosed('Pollers');

        openSubMenu('Pollers');

        cy.get('body').click();
        submenuShouldBeClosed('Pollers');

        openSubMenu('Pollers');

        cy.get('@pollerButton').click();
        submenuShouldBeClosed('Pollers');
      });

      it('should display the total number of pollers when there is no issues', () => {
        initialize();
        getElements();

        openSubMenu('Pollers');

        cy.findByTestId('poller-menu')
          .get('li:first-of-type')
          .should('contain.text', labelAllPollers)
          .should('contain.text', '12');

        cy.matchImageSnapshot();
      });

      it('should not display the total number if there is any issue', () => {
        initialize({
          pollersListIssues: {
            issues: {
              latency: {
                total: 1
              }
            }
          }
        });
        openSubMenu('Pollers');

        cy.findByTestId('poller-menu')
          .get('li:first-of-type')
          .should('not.contain.text', labelAllPollers);
      });

      it('should display alerting with the right text', () => {
        const issuesStubs = {
          database: {
            total: 1
          },
          latency: {
            total: 1
          },
          stability: {
            total: 1
          }
        };
        initialize({
          pollersListIssues: {
            issues: issuesStubs
          }
        });

        openSubMenu('Pollers');

        const expectedItems = [
          {
            qty: issuesStubs.database.total,
            text: labelDatabaseUpdatesNotActive
          },
          {
            qty: issuesStubs.latency.total,
            text: labelLatencyDetected
          },
          {
            qty: issuesStubs.stability.total,
            text: labelPollerNotRunning
          }
        ];

        cy.findByTestId('poller-menu')
          .findAllByRole('listitem')
          .as('items')
          .should('have.length', expectedItems.length);

        cy.get('@items').each(($el, index) => {
          cy.wrap($el)
            .should('contain.text', expectedItems[index].text)
            .should('contain.text', expectedItems[index].qty);
        });

        cy.matchImageSnapshot();
      });

      describe('configuration', () => {
        let userData;

        beforeEach(() => {
          const { result } = renderHook(() => useNavigation());
          userData = renderHook(() => useAtomValue(userAtom));

          act(() => {
            result.current.getNavigation();
          });
        });

        it('should NOT display a configuratiuon button if page is NOT allowed', () => {
          initialize();
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelConfigurePollers })
            .should('not.exist');
        });

        it('should display a configuratiuon button if page is allowed and navigate on click', () => {
          initialize({
            navigationList: {
              result: [
                {
                  children: [
                    {
                      groups: [],
                      is_react: false,
                      label: 'Resources Status',
                      options: null,
                      page: pollerConfigurationPageNumber,
                      show: true,
                      url: '/config/'
                    }
                  ]
                }
              ]
            }
          });
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelConfigurePollers })
            .should('be.visible')
            .click();

          cy.url().should(
            'include',
            `main.php?p=${pollerConfigurationPageNumber}`
          );
          cy.matchImageSnapshot();
        });

        it('display an export configuration button if user is allowed', () => {
          userData.result.current.isExportButtonEnabled = true;
          initialize();
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelExportConfiguration })
            .as('exportbutton')
            .should('be.visible');
          cy.matchImageSnapshot();
        });

        it('should open export configuration modal, and close it on cancel', () => {
          userData.result.current.isExportButtonEnabled = true;
          initialize();
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelExportConfiguration })
            .as('exportbutton');

          cy.get('@exportbutton').click();

          cy.findByRole('dialog').as('exportDialog').should('be.visible');

          cy.get('@exportDialog')
            .findByRole('heading', {
              name: labelExportAndReloadTheConfiguration
            })
            .should('be.visible');

          cy.get('@exportDialog')
            .findByRole('button', { name: labelCancel })
            .as('cancelExport')
            .should('be.visible');

          cy.matchImageSnapshot();

          cy.get('@cancelExport').click();

          cy.get('@exportDialog').should('not.exist');
        });

        it('should export configuration when clicking on export button in configuration modal', () => {
          userData.result.current.isExportButtonEnabled = true;
          initialize();
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelExportConfiguration })
            .as('exportbutton');

          cy.get('@exportbutton').click();

          cy.findByRole('dialog').as('exportDialog').should('be.visible');

          cy.get('@exportDialog')
            .findByRole('button', { name: labelExportAndReload })
            .as('dialogExportButton')
            .should('be.visible');

          cy.get('@dialogExportButton').click();

          cy.findAllByRole('alert').as('alerts').should('have.length', 2);

          cy.get('@alerts')
            .eq(0)
            .should('contain.text', labelExportingAndReloadingTheConfiguration)
            .should('be.visible');

          cy.get('@alerts')
            .eq(1)
            .should('contain.text', labelConfigurationExportedAndReloaded)
            .should('be.visible');

          cy.get('@exportDialog').should('not.exist');
          submenuShouldBeClosed('Pollers');
        });
      });
    });
  });
