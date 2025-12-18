import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';

import { ConnectionMode } from '../../models';
import ConnectionInitiated from './ConnectionInitiated';

interface Host {
  address: string;
  port: string;
  pollerCaCertificate: string;
  pollerCaName: string;
  token: null | { id: string; name: string };
}

interface TestValues {
  connectionMode: { id: ConnectionMode };
  configuration: {
    agentInitiated: boolean;
    pollerInitiated: boolean;
    otelPublicCertificate: string;
    otelCaCertificate: string;
    otelPrivateKey: string;
    tokens: Array<{
      name: string;
      id: number;
      inputValue?: string;
      testId?: string;
    }>;
    hosts: Array<Host>;
  };
}

const initialValues: TestValues = {
  configuration: {
    agentInitiated: false,
    hosts: [],
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    pollerInitiated: false,
    tokens: []
  },
  connectionMode: { id: ConnectionMode.secure }
};

const initialValuesWithAgentEnabled: TestValues = {
  ...initialValues,
  configuration: {
    ...initialValues.configuration,
    agentInitiated: true
  }
};

const initialValuesWithPollerEnabled: TestValues = {
  configuration: {
    agentInitiated: false,
    hosts: [
      {
        address: '',
        pollerCaCertificate: '',
        pollerCaName: '',
        port: '',
        token: null
      }
    ],
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    pollerInitiated: true,
    tokens: []
  },
  connectionMode: { id: ConnectionMode.secure }
};

const initialize = (values: TestValues = initialValues): void => {
  cy.mount({
    Component: (
      <BrowserRouter>
        <QueryClientProvider client={new QueryClient()}>
          <Formik initialValues={values} onSubmit={cy.stub()}>
            <ConnectionInitiated />
          </Formik>
        </QueryClientProvider>
      </BrowserRouter>
    )
  });
};

describe('ConnectionInitiated', () => {
  it('should render the component with default tab (agent)', () => {
    initialize();

    cy.contains('By agent').should('be.visible');
    cy.contains('By poller').should('be.visible');

    cy.get('[role="tabpanel"][id*="agent"]').should('be.visible');
    cy.get('[role="tabpanel"][id*="poller"]').should('not.be.visible');

    cy.get('.MuiSwitch-root').should('be.visible');
    cy.get('input[type="checkbox"]').should('not.be.checked');
    cy.contains('Enable').should('be.visible');

    cy.makeSnapshot();
  });

  it('should switch between tabs', () => {
    initialize();

    cy.contains('By poller').click();
    cy.get('[role="tabpanel"][id*="poller"]').should('be.visible');
    cy.get('[role="tabpanel"][id*="agent"]').should('not.be.visible');

    cy.contains('By agent').click();
    cy.get('[role="tabpanel"][id*="agent"]').should('be.visible');
    cy.get('[role="tabpanel"][id*="poller"]').should('not.be.visible');
  });

  it('should show done icon when agent configuration is enabled', () => {
    initialize(initialValuesWithAgentEnabled);

    cy.contains('By agent')
      .parent()
      .find('svg[data-testid="By agent selected"]')
      .should('be.visible');
  });

  it('should show done icon when poller configuration is enabled', () => {
    initialize(initialValuesWithPollerEnabled);

    cy.contains('By poller')
      .parent()
      .find('svg[data-testid="By poller selected"]')
      .should('be.visible');
  });

  it('should enable agent initiated configuration', () => {
    initialize();

    cy.get('.MuiSwitch-root').click();
    cy.get('input[type="checkbox"]').should('be.checked');

    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should(
      'be.visible'
    );
    cy.get('[data-testid="CA (.crt,.cer)"]').should('be.visible');
    cy.get('[data-testid="Private key (.key)"]').should('be.visible');
    cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('should disable agent initiated configuration', () => {
    initialize(initialValuesWithAgentEnabled);

    cy.get('.MuiSwitch-root').click();
    cy.get('input[type="checkbox"]').should('not.be.checked');

    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should(
      'not.exist'
    );
  });

  it('should enable poller initiated configuration', () => {
    initialize();

    cy.contains('By poller').click();

    cy.get('.MuiSwitch-root').click();
    cy.get('input[type="checkbox"]').should('be.checked');

    cy.get('[role="tabpanel"][id*="poller"]').should(
      'contain.text',
      'Monitored hosts'
    );
    cy.get('[role="tabpanel"][id*="poller"]').should(
      'contain.text',
      'Select host'
    );
  });

  it('should disable poller initiated configuration', () => {
    initialize(initialValuesWithPollerEnabled);

    cy.contains('By poller').click();

    cy.get('.MuiSwitch-root').click();
    cy.get('input[type="checkbox"]').should('not.be.checked');
  });

  it('should show tooltip icons for both tabs', () => {
    initialize();

    cy.get('svg[data-testid="InfoOutlinedIcon"]').should('have.length', 2);

    cy.get('svg[data-testid="InfoOutlinedIcon"]').first().trigger('mouseover');
  });

  it('should not render AgentInitiated when connection mode is not secure or insecure', () => {
    const valuesWithDifferentMode: TestValues = {
      configuration: {
        agentInitiated: true,
        hosts: [],
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        pollerInitiated: false,
        tokens: []
      },
      connectionMode: { id: ConnectionMode.none } // Assuming 'none' exists in ConnectionMode enum
    };

    initialize(valuesWithDifferentMode);

    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should(
      'not.exist'
    );
  });

  it('should render AgentInitiated when connection mode is secure', () => {
    const valuesWithSecureMode: TestValues = {
      configuration: {
        agentInitiated: true,
        hosts: [],
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        pollerInitiated: false,
        tokens: []
      },
      connectionMode: { id: ConnectionMode.secure }
    };

    initialize(valuesWithSecureMode);

    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should(
      'be.visible'
    );
  });

  it('should maintain tab state when switching between tabs', () => {
    initialize();

    cy.get('.MuiSwitch-root').click();

    cy.contains('By poller').click();
    cy.get('[role="tabpanel"][id*="poller"]').should('be.visible');

    cy.contains('By agent').click();
    cy.get('[role="tabpanel"][id*="agent"]').should('be.visible');

    cy.get('input[type="checkbox"]').should('be.checked');
    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should(
      'be.visible'
    );
  });
});
