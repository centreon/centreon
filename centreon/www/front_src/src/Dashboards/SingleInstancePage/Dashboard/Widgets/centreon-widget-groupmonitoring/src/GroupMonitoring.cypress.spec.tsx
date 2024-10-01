import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { Data } from '../../models';
import { getPublicWidgetEndpoint } from '../../utils';

import GroupMonitoring from './GroupMonitoring';
import { getEndpoint } from './api/endpoints';
import { PanelOptions } from './models';
import {
  labelCritical,
  labelDown,
  labelHosts,
  labelOk,
  labelPending,
  labelServices,
  labelUnknown,
  labelUnreachable,
  labelUp,
  labelWarning
} from './translatedLabels';

const getPanelDataGroups = ({
  type = 'host',
  withResource = false
}): Pick<Data, 'resources'> => ({
  resources: [
    {
      resourceType: `${type}-group`,
      resources: withResource ? [{ id: 1, name: 'Group 1' }] : []
    }
  ]
});

const allStatuses = ['1', '2', '4', '5', '6'];
const defaultStatuses = ['1', '2'];

const allResourceTypes = ['host', 'service'];
const onlyHostType = ['host'];
const onlyServiceType = ['service'];

const initialize = ({
  panelData = getPanelDataGroups({}),
  panelOptions,
  isFromPreview,
  isPublic = false
}: {
  isFromPreview?: boolean;
  isPublic?: boolean;
  panelData?: Pick<Data, 'resources'>;
  panelOptions: Omit<PanelOptions, 'refreshInterval'>;
}): { setPanelOptions } => {
  const setPanelOptions = cy.stub();

  const store = createStore();
  store.set(isOnPublicPageAtom, isPublic);

  cy.fixture('Widgets/GroupMonitoring/serviceGroups.json').then((response) => {
    cy.interceptAPIRequest({
      alias: 'serviceGroups',
      method: Method.GET,
      path: `./api/latest${getEndpoint('service-group')}**`,
      response
    });
  });

  cy.fixture('Widgets/GroupMonitoring/hostGroups.json').then((response) => {
    cy.interceptAPIRequest({
      alias: 'hostGroups',
      method: Method.GET,
      path: `./api/latest${getEndpoint('host-group')}**`,
      response
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidget',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        playlistHash: 'hash',
        widgetId: '1'
      })}`,
      response
    });
  });

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <TestQueryProvider>
          <BrowserRouter>
            <Provider store={store}>
              <GroupMonitoring
                dashboardId={1}
                globalRefreshInterval={{
                  interval: 15,
                  type: 'manual'
                }}
                id="1"
                isFromPreview={isFromPreview}
                panelData={panelData}
                panelOptions={{
                  ...panelOptions,
                  refreshInterval: 'default'
                }}
                playlistHash="hash"
                refreshCount={0}
                setPanelOptions={setPanelOptions}
              />
            </Provider>
          </BrowserRouter>
        </TestQueryProvider>
      </div>
    )
  });

  return {
    setPanelOptions
  };
};

describe('Public widget', () => {
  it('sends a request to the public API when the widget is displayed in a public page', () => {
    initialize({
      isPublic: true,
      panelOptions: {
        resourceTypes: allResourceTypes,
        statuses: defaultStatuses
      }
    });

    cy.waitForRequest('@getPublicWidget');
  });
});

describe('Group Monitoring', () => {
  it('displays the group monitoring widget with default options and the host group resource type is selected', () => {
    initialize({
      panelOptions: {
        resourceTypes: allResourceTypes,
        statuses: defaultStatuses
      }
    });

    cy.waitForRequest('@hostGroups');

    cy.contains('Host groups').should('be.visible');
    cy.contains(labelHosts).should('be.visible');
    cy.contains(labelServices).should('be.visible');
    cy.get(`[data-count="0"]`).should('have.length', 63);
    cy.get(`[data-status="${labelDown}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelCritical}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelWarning}"]`).should('have.length', 21);

    cy.fixture('Widgets/GroupMonitoring/hostGroups.json').then((response) => {
      response.result.forEach(({ name }) => {
        cy.contains(name).should('be.visible');
        cy.get(`[data-group="${name}"]`).should('have.length', 3);
      });
    });

    cy.makeSnapshot();
  });

  it('displays the group monitoring widget with default options and the service group resource type is selected', () => {
    initialize({
      panelData: getPanelDataGroups({ type: 'service' }),
      panelOptions: {
        resourceTypes: allResourceTypes,
        statuses: defaultStatuses
      }
    });

    cy.waitForRequest('@serviceGroups');

    cy.contains('Service groups').should('be.visible');
    cy.contains(labelHosts).should('be.visible');
    cy.contains(labelServices).should('be.visible');
    cy.get(`[data-count="0"]`).should('have.length', 63);
    cy.get(`[data-status="${labelDown}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelCritical}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelWarning}"]`).should('have.length', 21);

    cy.fixture('Widgets/GroupMonitoring/serviceGroups.json').then(
      (response) => {
        response.result.forEach(({ name }) => {
          cy.contains(name).should('be.visible');
          cy.get(`[data-group="${name}"]`).should('have.length', 3);
        });
      }
    );

    cy.makeSnapshot();
  });

  it('displays all statuses when the panel option is set', () => {
    initialize({
      panelOptions: {
        resourceTypes: allResourceTypes,
        statuses: allStatuses
      }
    });

    cy.get(`[data-status="${labelDown}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelUnreachable}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelUp}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelPending}"]`).should('have.length', 42);
    cy.get(`[data-status="${labelCritical}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelWarning}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelOk}"]`).should('have.length', 21);
    cy.get(`[data-status="${labelUnknown}"]`).should('have.length', 21);

    cy.get(`[data-status="${labelDown}"]`).should(
      'have.attr',
      'data-count',
      '0'
    );
    cy.get(`[data-status="${labelPending}"]`).should(
      'have.attr',
      'data-count',
      '0'
    );
    cy.get(`[data-status="${labelUp}"]`).should('have.attr', 'data-count', '1');
    cy.get(`[data-status="${labelUnreachable}"]`).should(
      'have.attr',
      'data-count',
      '0'
    );
    cy.get(`[data-status="${labelCritical}"]`).should(
      'have.attr',
      'data-count',
      '0'
    );
    cy.get(`[data-status="${labelWarning}"]`).should(
      'have.attr',
      'data-count',
      '0'
    );
    cy.get(`[data-status="${labelOk}"]`).should(
      'have.attr',
      'data-count',
      '101'
    );
    cy.get(`[data-status="${labelUnknown}"]`).should(
      'have.attr',
      'data-count',
      '3'
    );

    cy.makeSnapshot();
  });

  it('displays only the host column when the panel option is set', () => {
    initialize({
      panelOptions: {
        resourceTypes: onlyHostType,
        statuses: defaultStatuses
      }
    });

    cy.waitForRequest('@hostGroups');

    cy.contains(labelHosts).should('be.visible');
    cy.contains(labelServices).should('not.exist');

    cy.makeSnapshot();
  });

  it('displays only the service column when the panel option is set', () => {
    initialize({
      panelOptions: {
        resourceTypes: onlyServiceType,
        statuses: defaultStatuses
      }
    });

    cy.waitForRequest('@hostGroups');

    cy.contains(labelHosts).should('not.exist');
    cy.contains(labelServices).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Sorting', () => {
    it('sorts the name column when the header is clicked', () => {
      const { setPanelOptions } = initialize({
        panelOptions: {
          resourceTypes: allResourceTypes,
          statuses: defaultStatuses
        }
      });

      cy.waitForRequest('@hostGroups').then(({ request }) => {
        expect(request.url.href).to.include(
          'sort_by=%7B%22name%22%3A%22ASC%22%7D'
        );
      });

      cy.findByLabelText('Column Host groups')
        .click()
        .then(() => {
          expect(setPanelOptions).to.be.calledWith({
            sortField: 'name',
            sortOrder: 'desc'
          });
        });

      cy.makeSnapshot();
    });
  });

  describe('Pagination', () => {
    it('changes the page when the page button is clicked', () => {
      const { setPanelOptions } = initialize({
        panelOptions: {
          resourceTypes: allResourceTypes,
          statuses: defaultStatuses
        }
      });

      cy.waitForRequest('@hostGroups');

      cy.findByLabelText('Previous page').should('be.disabled');
      cy.findByLabelText('First page').should('be.disabled');

      cy.findByLabelText('Next page')
        .click()
        .then(() => {
          expect(setPanelOptions).to.be.calledWith({ limit: 10, page: 1 });
        });

      cy.makeSnapshot();
    });
  });

  describe('Limit', () => {
    it('changes the limit when a new limit is selected', () => {
      const { setPanelOptions } = initialize({
        panelOptions: {
          resourceTypes: allResourceTypes,
          statuses: defaultStatuses
        }
      });

      cy.waitForRequest('@hostGroups');

      cy.get('[id="Rows per page"]')
        .parent()
        .get('input')
        .should('have.value', 10);

      cy.get('[id="Rows per page"]').click();
      cy.contains('60')
        .click()
        .then(() => {
          expect(setPanelOptions).to.be.calledWith({ limit: 60 });
        });

      cy.makeSnapshot();
    });
  });

  describe('Links', () => {
    it('redirects to Resources Status when the group name is clicked', () => {
      initialize({
        panelOptions: {
          resourceTypes: allResourceTypes,
          statuses: defaultStatuses
        }
      });

      cy.contains('Linux-Servers')
        .should(
          'have.attr',
          'href',
          '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%2C%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%2C%7B%22id%22%3A%22metaservice%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
        )
        .should('have.attr', 'target', '_blank')
        .should('have.attr', 'rel', 'noopener noreferrer');
      cy.get('[data-group="Linux-Servers"]')
        .should(
          'have.attr',
          'href',
          '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
        )
        .should('have.attr', 'target', '_blank')
        .should('have.attr', 'rel', 'noopener noreferrer');
    });

    it('does not display links to Resources Status when the preview mode is enabled', () => {
      initialize({
        isFromPreview: true,
        panelOptions: {
          resourceTypes: allResourceTypes,
          statuses: defaultStatuses
        }
      });

      cy.contains('Linux-Servers').should(
        'not.have.attr',
        'href',
        '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%2C%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%2C%7B%22id%22%3A%22metaservice%22%2C%22name%22%3A%22Meta%20service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
      );
      cy.get('[data-group="Linux-Servers"]').should(
        'not.have.attr',
        'href',
        '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22Linux-Servers%22%2C%22name%22%3A%22Linux-Servers%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true'
      );
    });
  });
});
