import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import { searchAtom } from '../../Filter/filterAtoms';
import { Resource } from '../../models';
import AnalyzeResources from '../AnalyzeResources/AnalyzeResources';
import AskAiFilter from '../AskAiFilter/AskAiFilter';
import ExplainResource from '../ExplainResource/ExplainResource';
import {
  aiAlertCorrelationEndpoint,
  aiAvailabilityEndpoint,
  aiIncidentSummaryEndpoint,
  aiResourceExplanationEndpoint,
  aiResourceFilterEndpoint
} from '../endpoints';
import SummarizeIncident from '../SummarizeIncident/SummarizeIncident';
import {
  labelAiCouldNotTranslate,
  labelAnalyze,
  labelAskAi,
  labelAskAiApply,
  labelAskAiPlaceholder,
  labelCopy,
  labelExplain,
  labelSummarize
} from '../translatedLabels';

const initializeComponent = (
  component: JSX.Element,
  availability: { is_available: boolean; reason?: string | null } = {
    is_available: true
  }
): ReturnType<typeof createStore> => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.interceptAPIRequest({
    alias: 'getAiAvailability',
    method: Method.GET,
    path: `**${aiAvailabilityEndpoint}`,
    response: availability
  });

  const store = createStore();

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>{component}</Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });

  return store;
};

describe('AskAiFilter', () => {
  it('applies the translated search expression', () => {
    cy.interceptAPIRequest({
      alias: 'translateFilter',
      method: Method.POST,
      path: `**${aiResourceFilterEndpoint}`,
      response: { search: 'type:service status:critical' }
    });

    const store = initializeComponent(<AskAiFilter />);

    cy.findByTestId(labelAskAi).click();
    cy.findByTestId(labelAskAiPlaceholder).type('critical services');
    cy.findByTestId(labelAskAiApply).click();

    cy.waitForRequest('@translateFilter').then(({ request }) => {
      expect(request.body.prompt).to.equal('critical services');
    });

    cy.wrap(null).should(() => {
      // the filter rebuild can leave a trailing separator in the search string
      expect(store.get(searchAtom).trim()).to.equal(
        'type:service status:critical'
      );
    });

    cy.makeSnapshot();
  });

  it('warns when no filter could be derived', () => {
    cy.interceptAPIRequest({
      alias: 'translateFilter',
      method: Method.POST,
      path: `**${aiResourceFilterEndpoint}`,
      response: { search: '' }
    });

    initializeComponent(<AskAiFilter />);

    cy.findByTestId(labelAskAi).click();
    cy.findByTestId(labelAskAiPlaceholder).type('hello there');
    cy.findByTestId(labelAskAiApply).click();

    cy.waitForRequest('@translateFilter');

    cy.contains(labelAiCouldNotTranslate);
  });
});

describe('ExplainResource', () => {
  const details = {
    command_line: 'check_icmp -H 10.0.0.1 -w 200,20% -c 500,60%',
    duration: '2h 31m',
    information: 'CRITICAL - Packet loss = 100%',
    name: 'Ping',
    parent: { id: 1, name: 'db-server-01' },
    performance_data: 'rta=0.000ms;200;500;0;',
    status: { name: 'CRITICAL', severity_code: 1 },
    tries: '3/3 (Hard)',
    type: 'service'
  } as unknown as ResourceDetails;

  it('displays the explanation in a dialog', () => {
    cy.interceptAPIRequest({
      alias: 'explainResource',
      method: Method.POST,
      path: `**${aiResourceExplanationEndpoint}`,
      response: {
        explanation: '## What this means\n\n- The ping check lost all packets.',
        model: 'claude-sonnet-4-6'
      }
    });

    initializeComponent(<ExplainResource details={details} />);

    cy.findByTestId(labelExplain).click();

    cy.waitForRequest('@explainResource').then(({ request }) => {
      expect(request.body.name).to.equal('Ping');
      expect(request.body.resource_type).to.equal('service');
      expect(request.body.status).to.equal('CRITICAL');
      expect(request.body.information).to.equal(
        'CRITICAL - Packet loss = 100%'
      );
      expect(request.body.parent_name).to.equal('db-server-01');
    });

    cy.contains('The ping check lost all packets');
    cy.contains('claude-sonnet-4-6');
    // the title carries the host so a service is unambiguous
    cy.contains('on db-server-01');

    cy.makeSnapshot();
  });

  it('is hidden when no AI provider is enabled', () => {
    initializeComponent(<ExplainResource details={details} />, {
      is_available: false,
      reason: 'no_provider'
    });

    cy.waitForRequest('@getAiAvailability');

    cy.findByTestId(labelExplain).should('not.exist');
  });

  it('is visible but disabled when the AI budget is exhausted', () => {
    initializeComponent(<ExplainResource details={details} />, {
      is_available: false,
      reason: 'budget_exhausted'
    });

    cy.waitForRequest('@getAiAvailability');

    cy.findByTestId(labelExplain).should('be.visible').and('be.disabled');
  });
});

describe('SummarizeIncident', () => {
  const timelineEndpoint = '/monitoring/hosts/1/services/1/timeline';

  const details = {
    duration: '2h 31m',
    information: 'CRITICAL - Packet loss = 100%',
    links: { endpoints: { timeline: timelineEndpoint } },
    name: 'Ping',
    parent: { id: 1, name: 'db-server-01' },
    status: { name: 'CRITICAL', severity_code: 1 },
    type: 'service'
  } as unknown as ResourceDetails;

  const timelineListing = (total: number): object => ({
    meta: { limit: 100, page: 1, search: {}, sort_by: {}, total },
    result: [
      {
        contact: { name: 'admin' },
        content: 'Looking into it',
        date: '2026-06-12T08:10:00+02:00',
        id: 2,
        type: 'acknowledgement'
      },
      {
        content: 'Packet loss = 100%',
        date: '2026-06-12T08:00:00+02:00',
        id: 1,
        status: { name: 'CRITICAL', severity_code: 1 },
        tries: 3,
        type: 'event'
      }
    ]
  });

  const interceptSummary = (total = 2): void => {
    cy.interceptAPIRequest({
      alias: 'listTimeline',
      method: Method.GET,
      path: `**${timelineEndpoint}**`,
      response: timelineListing(total)
    });

    cy.interceptAPIRequest({
      alias: 'summarizeIncident',
      method: Method.POST,
      path: `**${aiIncidentSummaryEndpoint}`,
      response: {
        model: 'claude-sonnet-4-6',
        summary: '## Summary\n\n- Ping lost all packets and was acknowledged.'
      }
    });
  };

  it('displays the summary built from the recent timeline events', () => {
    interceptSummary();

    initializeComponent(<SummarizeIncident details={details} />);

    cy.findByTestId(labelSummarize).click();

    cy.waitForRequest('@listTimeline').then(({ request }) => {
      expect(request.url.search).to.contain('limit=100');
    });

    cy.waitForRequest('@summarizeIncident').then(({ request }) => {
      expect(request.body.name).to.equal('Ping');
      expect(request.body.status).to.equal('CRITICAL');
      expect(request.body.is_truncated).to.equal(false);
      // events are sent oldest first
      expect(request.body.events[0].type).to.equal('event');
      expect(request.body.events[0].status).to.equal('CRITICAL');
      expect(request.body.events[1].type).to.equal('acknowledgement');
      expect(request.body.events[1].author).to.equal('admin');
    });

    cy.contains('Ping lost all packets and was acknowledged');
    cy.contains('claude-sonnet-4-6');
    // the title carries the host so a service is unambiguous
    cy.contains('on db-server-01');

    cy.makeSnapshot();
  });

  it('copies the summary to the clipboard', () => {
    interceptSummary();

    initializeComponent(<SummarizeIncident details={details} />);

    cy.window().then((win) => {
      cy.stub(win.navigator.clipboard, 'writeText')
        .as('writeText')
        .resolves(undefined);
    });

    cy.findByTestId(labelSummarize).click();
    cy.waitForRequest('@summarizeIncident');

    cy.findByTestId(labelCopy).click();

    cy.get('@writeText').should(
      'have.been.calledWith',
      '## Summary\n\n- Ping lost all packets and was acknowledged.'
    );
  });

  it('flags the summary as truncated when more events exist in the window', () => {
    interceptSummary(150);

    initializeComponent(<SummarizeIncident details={details} />);

    cy.findByTestId(labelSummarize).click();

    cy.waitForRequest('@summarizeIncident').then(({ request }) => {
      expect(request.body.is_truncated).to.equal(true);
    });
  });
});

describe('AnalyzeResources', () => {
  const resources = [
    {
      duration: '2h 31m',
      information: 'CRITICAL - Packet loss = 100%',
      name: 'Ping',
      parent: { name: 'db-server-01' },
      status: { name: 'CRITICAL', severity_code: 1 },
      type: 'service'
    },
    {
      information: 'Host unreachable',
      name: 'db-server-01',
      status: { name: 'DOWN', severity_code: 1 },
      type: 'host'
    }
  ] as unknown as Array<Resource>;

  it('analyzes the selected resources together', () => {
    cy.interceptAPIRequest({
      alias: 'correlateAlerts',
      method: Method.POST,
      path: `**${aiAlertCorrelationEndpoint}`,
      response: {
        analysis: '## Likely root cause\n\n- The host db-server-01 is down.',
        model: 'claude-sonnet-4-6'
      }
    });

    initializeComponent(<AnalyzeResources resources={resources} />);

    cy.findByTestId(labelAnalyze).click();

    cy.waitForRequest('@correlateAlerts').then(({ request }) => {
      expect(request.body.resources).to.have.length(2);
      expect(request.body.resources[0].resource_type).to.equal('service');
      expect(request.body.resources[0].parent_name).to.equal('db-server-01');
      expect(request.body.resources[1].status).to.equal('DOWN');
    });

    cy.contains('The host db-server-01 is down');
    cy.contains('claude-sonnet-4-6');

    cy.makeSnapshot();
  });

  it('disables the action with fewer than two resources', () => {
    initializeComponent(<AnalyzeResources resources={[resources[0]]} />);

    cy.waitForRequest('@getAiAvailability');

    cy.findByTestId(labelAnalyze).should('be.disabled');
  });

  it('is hidden when no AI provider is enabled', () => {
    initializeComponent(<AnalyzeResources resources={resources} />, {
      is_available: false,
      reason: 'no_provider'
    });

    cy.waitForRequest('@getAiAvailability');

    cy.findByTestId(labelAnalyze).should('not.exist');
  });
});
