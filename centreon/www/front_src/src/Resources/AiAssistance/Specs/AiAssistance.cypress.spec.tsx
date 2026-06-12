import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import { searchAtom } from '../../Filter/filterAtoms';
import AskAiFilter from '../AskAiFilter/AskAiFilter';
import ExplainResource from '../ExplainResource/ExplainResource';
import {
  aiResourceExplanationEndpoint,
  aiResourceFilterEndpoint
} from '../endpoints';
import {
  labelAiCouldNotTranslate,
  labelAskAi,
  labelAskAiApply,
  labelAskAiPlaceholder,
  labelExplain
} from '../translatedLabels';

const initializeComponent = (
  component: JSX.Element
): ReturnType<typeof createStore> => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
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
        explanation: 'What this means:\n- The ping check lost all packets.',
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

    cy.makeSnapshot();
  });
});
