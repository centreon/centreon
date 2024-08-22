import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { T, always, cond, equals } from 'ramda';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import {
  additionalResourcesAtom,
  isOnPublicPageAtom,
  userAtom
} from '@centreon/ui-context';

import { StatusGridWrapper } from '..';
import { getPublicWidgetEndpoint } from '../../../utils';
import { getStatusesEndpoint } from '../StatusGridCondensed/api/endpoints';
import { Data, PanelOptions } from '../StatusGridStandard/models';
import {
  baIndicatorsEndpoint,
  businessActivitiesEndpoint,
  getBAEndpoint,
  getBooleanRuleEndpoint
} from '../api/endpoints';

import {
  baCondensedOptions,
  baOptions,
  baResources,
  bvResources
} from './testUtils';

interface Props {
  data: Data;
  isPublic?: boolean;
  options: PanelOptions;
}

const additionalResources = [
  {
    availableResourceTypeOptions: [],
    baseEndpoint: '/bam/monitoring/business-activities',
    label: 'Business Activity',
    resourceType: 'business-activity'
  },
  {
    availableResourceTypeOptions: [
      { id: 'business-activity', name: 'Business Activity' }
    ],
    baseEndpoint: '/bam/monitoring/business-views',
    label: 'Business View',
    resourceType: 'business-view'
  }
];

const baTestCases = [
  {
    calculationMethod: 'Worst Status',
    id: 1,
    status: 'ok',
    testFunction: () => {
      cy.contains('Calculation method : Worst Status').should('be.visible');
      cy.contains(
        'All KPIs on this Business Activity are working fine.'
      ).should('be.visible');

      cy.get('[data-resourceName=ba1]').should(
        'have.css',
        'color',
        'rgb(136, 185, 34)'
      );
    }
  },
  {
    calculationMethod: 'Impact',
    id: 1,
    status: 'ok',
    testFunction: () => {
      cy.contains('Calculation method : Impact').should('be.visible');

      cy.get('[data-resourceName=ba1').should(
        'have.css',
        'color',
        'rgb(136, 185, 34)'
      );

      cy.contains('State information').should('be.visible');
      cy.contains('Health').should('be.visible');
      cy.contains('100%').should('be.visible');
      cy.contains('Warning threshold').should('be.visible');
      cy.contains('80%').should('be.visible');
      cy.contains('Critical threshold').should('be.visible');
      cy.contains('70%').should('be.visible');

      cy.contains(
        'All KPIs on this Business Activity are working fine.'
      ).should('be.visible');
    }
  },
  {
    calculationMethod: 'Ratio',
    id: 1,
    status: 'ok',
    testFunction: () => {
      cy.contains('Calculation method : Ratio').should('be.visible');

      cy.get('[data-resourceName=ba1').should(
        'have.css',
        'color',
        'rgb(136, 185, 34)'
      );

      cy.contains('State information').should('be.visible');
      cy.contains('Critical KPIs').should('be.visible');
      cy.contains('0%').should('be.visible');
      cy.contains('Warning threshold').should('be.visible');
      cy.contains('75%').should('be.visible');
      cy.contains('Critical threshold').should('be.visible');
      cy.contains('80%').should('be.visible');

      cy.contains(
        'All KPIs on this Business Activity are working fine.'
      ).should('be.visible');
    }
  },
  {
    calculationMethod: 'Impact',
    id: 4,
    status: 'critical',
    testFunction: () => {
      cy.contains('Calculation method : Impact').should('be.visible');

      cy.get('[data-resourceName=ba4]').should(
        'have.css',
        'color',
        'rgb(255, 102, 102)'
      );

      cy.contains('State information').should('be.visible');
      cy.contains('Health').should('be.visible');
      cy.contains('100%').should('be.visible');
      cy.contains('Warning threshold').should('be.visible');
      cy.contains('80%').should('be.visible');
      cy.contains('Critical threshold').should('be.visible');
      cy.contains('70%').should('be.visible');

      cy.contains('KPIs').should('be.visible');
      cy.contains('Ping').should('be.visible');
      cy.contains('12%').should('be.visible');
      cy.contains('15%').should('be.visible');
      cy.contains('boolean 2').should('be.visible');
      cy.contains('75%').should('be.visible');
      cy.contains('100%').should('be.visible');
      cy.contains('1/3 KPIs are working fine.').should('be.visible');
    }
  },
  {
    calculationMethod: 'Ratio',
    id: 5,
    status: 'critical',
    testFunction: () => {
      cy.contains('Calculation method : Ratio').should('be.visible');

      cy.get('[data-resourceName=ba5]').should(
        'have.css',
        'color',
        'rgb(255, 102, 102)'
      );

      cy.contains('State information').should('be.visible');
      cy.contains('Critical KPIs').should('be.visible');
      cy.contains('0%').should('be.visible');
      cy.contains('Warning threshold').should('be.visible');
      cy.contains('75%').should('be.visible');
      cy.contains('Critical threshold').should('be.visible');
      cy.contains('80%').should('be.visible');

      cy.contains('KPIs').should('be.visible');
      cy.contains('ba1').should('be.visible');
      cy.contains('1/3 KPIs are working fine.').should('be.visible');
    }
  }
];

const initialize = ({ options, data, isPublic = false }: Props): void => {
  const store = createStore();
  store.set(userAtom, { locale: 'en_US', timezone: 'Europe/Paris' });
  store.set(isOnPublicPageAtom, isPublic);

  store.set(additionalResourcesAtom, additionalResources);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <BrowserRouter>
            <div style={{ height: '100vh', width: '100vw' }}>
              <StatusGridWrapper
                dashboardId={1}
                globalRefreshInterval={{
                  interval: 30,
                  type: 'manual'
                }}
                id="1"
                panelData={data}
                panelOptions={options}
                playlistHash="hash"
                refreshCount={0}
              />
            </div>
          </BrowserRouter>
        </Provider>
      </TestQueryProvider>
    )
  });
};

const baRequests = ({ calculationMethod = 'Worst Status', id = 1 }): void => {
  cy.fixture('Widgets/StatusGrid/businessActivities.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getBAsResources',
      method: Method.GET,
      path: `./api/latest${businessActivitiesEndpoint}?**`,
      response: data
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidgetStandard',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        playlistHash: 'hash',
        widgetId: '1'
      })}`,
      response: data
    });
  });

  const baResponseURL = cond([
    [equals('Impact'), always('Widgets/StatusGrid/impactBA.json')],
    [equals('Ratio'), always('Widgets/StatusGrid/ratioBA.json')],
    [T, always('Widgets/StatusGrid/worstStatusBA.json')]
  ])(calculationMethod);

  const baResponseURLByID = cond([
    [equals(4), always('Widgets/StatusGrid/criticalImpactBA.json')],
    [equals(5), always('Widgets/StatusGrid/criticalRatioBA.json')],
    [T, always(baResponseURL)]
  ])(id);

  cy.fixture(baResponseURLByID).then((data) => {
    cy.interceptAPIRequest({
      alias: 'getBATooltipDetails',
      method: Method.GET,
      path: `./api/latest${getBAEndpoint(id)}`,
      response: data
    });
  });
};

const indicatorsRequests = (): void => {
  cy.fixture('Widgets/StatusGrid/indicators.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getKPIsResources',
      method: Method.GET,
      path: `./api/latest${baIndicatorsEndpoint}?**`,
      response: data
    });
  });

  cy.fixture('Widgets/StatusGrid/booleanRuleDetails.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getBooleanRuleDetails',
      method: Method.GET,
      path: `./api/latest${getBooleanRuleEndpoint(1)}`,
      response: data
    });
  });
};

const statusRequests = (type): void => {
  const endpoint = equals(type, 'business activities')
    ? getStatusesEndpoint('business-view')
    : getStatusesEndpoint('business-activity');

  cy.fixture('Widgets/StatusGrid/bamCondensed.json').then((data) => {
    cy.interceptAPIRequest({
      alias: `getStatuses/${type}`,
      delay: 2000,
      method: Method.GET,
      path: `./api/latest${endpoint}?**`,
      response: data
    });

    cy.interceptAPIRequest({
      alias: 'getPublicWidgetCondensed',
      method: Method.GET,
      path: `./api/latest${getPublicWidgetEndpoint({
        dashboardId: 1,
        playlistHash: 'hash',
        widgetId: '1'
      })}`,
      response: data
    });
  });

  cy.fixture('Widgets/StatusGrid/businessActivities.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getStatusesTooltipDetails',
      method: Method.GET,
      path: `./api/latest${businessActivitiesEndpoint}?**`,
      response: data
    });
  });

  cy.fixture('Widgets/StatusGrid/indicators.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getKPIsStatusesTooltipDetails',
      method: Method.GET,
      path: `./api/latest${baIndicatorsEndpoint}?**`,
      response: data
    });
  });
};

describe('Public widget', () => {
  it('sends a request to the public API when the widget is displayed in a public page and the standard view is enabled', () => {
    baRequests({});
    initialize({
      data: { resources: bvResources },
      isPublic: true,
      options: baOptions
    });

    cy.waitForRequest('@getPublicWidgetStandard');
  });

  it('sends a request to the public API when the widget is displayed in a public page and the condensed view is enabled', () => {
    statusRequests('business activities');
    initialize({
      data: { resources: bvResources },
      isPublic: true,
      options: baCondensedOptions
    });

    cy.waitForRequest('@getPublicWidgetCondensed');
  });
});

describe('Business activities', () => {
  beforeEach(() => {
    cy.clock(new Date(2021, 1, 1, 0, 0, 0), ['Date']);

    initialize({
      data: { resources: bvResources },
      isPublic: false,
      options: baOptions
    });
  });

  it('displays tiles', () => {
    baRequests({});

    cy.waitForRequest('@getBAsResources');

    cy.contains('ba1').should('be.visible');
    cy.get('[data-status="ok"]').should('be.visible');
    cy.get('[data-status="ok"]')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(136, 185, 34)');

    cy.contains('ba2').should('be.visible');
    cy.get('[data-status="pending"]').should('be.visible');
    cy.get('[data-status="pending"]')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(30, 190, 179)');

    cy.contains('ba3').should('be.visible');
    cy.get('[data-status="unknown"]').should('be.visible');
    cy.get('[data-status="unknown"]')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(227, 227, 227)');

    cy.makeSnapshot();
  });

  baTestCases.forEach(({ id, calculationMethod, testFunction, status }) => {
    it(`${calculationMethod}-${status}: displays business activity informations when the mouse is over a tile`, () => {
      baRequests({ calculationMethod, id });

      cy.contains(`ba${id}`).trigger('mouseover');

      cy.waitForRequest('@getBATooltipDetails');

      testFunction();

      cy.makeSnapshot();
    });
  });
});

describe('Indicators', () => {
  beforeEach(() => {
    cy.clock(new Date(2021, 1, 1, 0, 0, 0), ['Date']);

    indicatorsRequests();

    initialize({
      data: { resources: baResources },
      isPublic: false,
      options: baOptions
    });
  });

  it('displays tiles', () => {
    cy.waitForRequest('@getKPIsResources');

    cy.contains('Ping').should('be.visible');
    cy.get('[data-status="ok"]').should('be.visible');
    cy.get('[data-status="ok"]')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(136, 185, 34)');

    cy.contains('Memory').should('be.visible');
    cy.get('[data-status="unknown"]').should('be.visible');
    cy.get('[data-status="unknown"]')
      .parent()
      .parent()
      .should('have.css', 'background-color', 'rgb(227, 227, 227)');

    cy.makeSnapshot();
  });
  it('displays boolean rule informations when the mouse is over boolean rule tile', () => {
    cy.contains('boolean 1').trigger('mouseover');

    cy.waitForRequest('@getBooleanRuleDetails');

    cy.get('[data-resourceName="boolean 1"]').should(
      'have.css',
      'color',
      'rgb(136, 185, 34)'
    );

    cy.contains('Parent: ba1');
    cy.contains('Impact applied when: true');
    cy.contains('Expression in ok.');
    cy.contains('Click here for details').should(
      'have.attr',
      'href',
      '/main.php?p=62611&o=c&boolean_id=1'
    );

    cy.makeSnapshot();
  });
});

describe('Condensed view', () => {
  describe('Business activities', () => {
    beforeEach(() => {
      cy.clock(new Date(2021, 1, 1, 0, 0, 0), ['Date']);

      statusRequests('business activities');

      initialize({
        data: {
          resources: bvResources
        },
        isPublic: false,
        options: baCondensedOptions
      });
    });

    it('displays status tiles', () => {
      cy.get('[data-skeleton="true"]').should('be.visible');

      cy.waitForRequest('@getStatuses/business activities');

      cy.contains('40 business activities').should('be.visible');
      cy.contains('ok').should('be.visible');
      cy.contains('22').should('be.visible');
      cy.contains('critical').should('be.visible');
      cy.contains('10').should('be.visible');
      cy.contains('pending').should('be.visible');
      cy.contains('4').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the tooltip when the success card is hovered', () => {
      cy.get('[data-label="ok"]').trigger('mouseover');
      cy.contains('February 1, 2021').should('be.visible');
      cy.contains('22/40 business activities are working fine.').should(
        'be.visible'
      );

      cy.makeSnapshot();
    });

    it('displays the tooltip when the problem card is hovered', () => {
      cy.get('[data-label="critical"]').trigger('mouseover');

      cy.waitForRequestAndVerifyQueries({
        queries: [
          {
            key: 'search',
            value:
              '{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"status":{"0":"critical"}}]}]}'
          }
        ],
        requestAlias: 'getStatusesTooltipDetails'
      });

      cy.contains('Status: Critical').should('be.visible');
      cy.contains('10 business activities').should('be.visible');
      cy.contains('ba1').should('be.visible');
      cy.contains('ba2').should('be.visible');
      cy.contains('ba3').should('be.visible');
      cy.contains('February 1, 2021').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the tooltip when the pending card is hovered', () => {
      cy.get('[data-label="pending"]').trigger('mouseover');

      cy.waitForRequestAndVerifyQueries({
        queries: [
          {
            key: 'search',
            value:
              '{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"status":{"0":"pending"}}]}]}'
          }
        ],
        requestAlias: 'getStatusesTooltipDetails'
      });

      cy.contains('Status: Pending').should('be.visible');
      cy.contains('4 business activities').should('be.visible');
      cy.contains('ba1').should('be.visible');
      cy.contains('ba2').should('be.visible');
      cy.contains('ba3').should('be.visible');
      cy.contains('February 1, 2021').should('be.visible');

      cy.makeSnapshot();
    });

    it('navigates to business activity page when a status card is clicked', () => {
      cy.get('[data-label="ok"]').should(
        'have.attr',
        'href',
        '/main.php?p=20701&status=ok'
      );

      cy.get('[data-label="critical"]').should(
        'have.attr',
        'href',
        '/main.php?p=20701&status=critical'
      );
    });
  });

  describe('KPIs', () => {
    beforeEach(() => {
      cy.clock(new Date(2021, 1, 1, 0, 0, 0), ['Date']);

      statusRequests('KPIS');

      initialize({
        data: {
          resources: baResources
        },
        isPublic: false,
        options: baCondensedOptions
      });
    });

    it('displays status tiles', () => {
      cy.get('[data-skeleton="true"]').should('be.visible');

      cy.waitForRequest('@getStatuses/KPIS');

      cy.contains('40 KPIS').should('be.visible');
      cy.contains('ok').should('be.visible');
      cy.contains('22').should('be.visible');
      cy.contains('critical').should('be.visible');
      cy.contains('10').should('be.visible');
      cy.contains('pending').should('be.visible');
      cy.contains('4').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the tooltip when the success card is hovered', () => {
      cy.get('[data-label="ok"]').trigger('mouseover');
      cy.contains('February 1, 2021').should('be.visible');
      cy.contains('22/40 KPIS are working fine.').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the tooltip when the problem card is hovered', () => {
      cy.get('[data-label="critical"]').trigger('mouseover');

      cy.waitForRequestAndVerifyQueries({
        queries: [
          {
            key: 'search',
            value:
              '{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"business_activity.name":{"$rg":"^ba1$"}}]},{"$or":[{"status":{"0":"critical"}}]}]}'
          }
        ],
        requestAlias: 'getKPIsStatusesTooltipDetails'
      });

      cy.contains('Status: Critical').should('be.visible');
      cy.contains('10 KPIS').should('be.visible');
      cy.contains('Memory').should('be.visible');
      cy.contains('Ping').should('be.visible');
      cy.contains('February 1, 2021').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the tooltip when the pending card is hovered', () => {
      cy.get('[data-label="pending"]').trigger('mouseover');

      cy.waitForRequestAndVerifyQueries({
        queries: [
          {
            key: 'search',
            value:
              '{"$and":[{"$or":[{"business_view.name":{"$rg":"^bv1$"}}]},{"$or":[{"business_activity.name":{"$rg":"^ba1$"}}]},{"$or":[{"status":{"0":"pending"}}]}]}'
          }
        ],
        requestAlias: 'getKPIsStatusesTooltipDetails'
      });

      cy.contains('Status: Pending').should('be.visible');
      cy.contains('4 KPIS').should('be.visible');
      cy.contains('Memory').should('be.visible');
      cy.contains('Ping').should('be.visible');
      cy.contains('February 1, 2021').should('be.visible');

      cy.makeSnapshot();
    });

    it('navigates to indicators page when a status card is clicked', () => {
      cy.get('[data-label="ok"]').should(
        'have.attr',
        'href',
        '/main.php?p=62606'
      );
    });
  });
});
