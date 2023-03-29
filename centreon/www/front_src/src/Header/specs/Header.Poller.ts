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

import {
  initialize,
  submenuShouldBeClosed,
  submenuShouldBeOpened,
  openSubMenu
} from './Header.testUtils';

const getElements = (): void => {
  cy.findByRole('button', { name: labelPollers, timeout: 5000 }).as(
    'pollerButton'
  );

  cy.findByRole('status', { name: 'database' }).as('databaseIndicator');
  cy.findByRole('status', { name: 'latency' }).as('latencyIndicator');
};

export default (): void =>
  describe('Pollers', () => {
    describe('responsive behaviors', () => {
      it("hides the button's text at smaller screen size", () => {
        initialize();
        getElements();
        cy.viewport(1024, 300);
        cy.get('@pollerButton').within(() => {
          cy.findByText('Pollers').should('not.be.visible');
          cy.findByTestId('ExpandLessIcon').should('be.visible');
          cy.findByTestId('DeviceHubIcon').should('be.visible');
        });
      });

      it('hides top counters at very small size', () => {
        initialize();
        getElements();

        cy.viewport(599, 300);
        cy.get('@databaseIndicator').should('not.be.visible');
        cy.get('@latencyIndicator').should('not.be.visible');
      });
    });

    describe('top status indicators', () => {
      it('displays green indicators when no issues are detected', () => {
        initialize({
          pollersListIssues: {
            issues: []
          }
        });
        getElements();

        cy.get('@databaseIndicator').should(
          'contain.text',
          labelDatabaseUpdateAndActive
        );

        cy.get('@latencyIndicator').should(
          'contain.text',
          labelNoLatencyDetected
        );
      });

      describe('database', () => {
        it('alert user about database critical issues', () => {
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

        it('validates database with no issues', () => {
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
        it('alerts user about latency critical issues', () => {
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

        it('validates latency with no issues', () => {
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

      describe('stability', () => {
        it('validates stability when there are issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                stability: {
                  critical: {
                    poller: [{ id: 1, name: 'poller1', since: '' }],
                    total: 1
                  },
                  total: 1
                }
              }
            }
          });
          getElements();

          cy.get('@databaseIndicator').should(
            'contain.text',
            labelDatabaseUpdateAndActive
          );

          cy.get('@latencyIndicator').should(
            'contain.text',
            labelNoLatencyDetected
          );
        });

        it('validates stability with no issues', () => {
          initialize({
            pollersListIssues: {
              issues: {
                stability: {
                  critical: {
                    poller: [],
                    total: 0
                  },
                  total: 0,
                  warning: {
                    poller: [],
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

          cy.get('@latencyIndicator').should(
            'contain.text',
            labelNoLatencyDetected
          );
        });
      });
    });

    describe('sub menu', () => {
      it('displays a button to open the submenu', () => {
        initialize();
        getElements();
        submenuShouldBeClosed('Pollers');
        cy.get('@pollerButton').should('be.visible');
        cy.get('@pollerButton').click();
        submenuShouldBeOpened('Pollers');
        cy.matchImageSnapshot();
      });

      it('closes the submenu by clicking outside, using esc key, or clicking again on the button', () => {
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

      it('displays the total number of pollers when there is no issues', () => {
        initialize();
        getElements();

        openSubMenu('Pollers');

        cy.findByTestId('poller-menu')
          .get('li:first-of-type')
          .should('contain.text', labelAllPollers)
          .should('contain.text', '12');

        cy.matchImageSnapshot();
      });

      it('hides the total number if there is not any issue', () => {
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

      it('displays alerting with the right text', () => {
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

        it('hides the configuratiuon button if the user is not allowed to access the configuration page', () => {
          initialize();
          openSubMenu('Pollers');

          cy.findByTestId('poller-menu')
            .findAllByRole('listitem')
            .last()
            .findByRole('button', { name: labelConfigurePollers })
            .should('not.exist');
        });

        it('displays a configuratiuon button if the user is allowed to access the configuration page', () => {
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

        it('displays the export configuration button if user is allowed', () => {
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

        it('opens the export configuration’s modal, and close it on clicking the cancel button', () => {
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

        it('exports the configuration when clicking on the export button in the configuration modal', () => {
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
