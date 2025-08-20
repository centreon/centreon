import { Formik } from 'formik';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router';

import ConnectionInitiated from './ConnectionInitiated';
import { ConnectionMode } from '../../models';

interface Host {
    address: string;
    port: string;
    pollerCaCertificate: string;
    pollerCaName: string;
    token: any;
}

interface TestValues {
    connectionMode: { id: ConnectionMode };
    configuration: {
        agentInitiated: boolean;
        pollerInitiated: boolean;
        otelPublicCertificate: string;
        otelCaCertificate: string;
        otelPrivateKey: string;
        tokens: any[];
        hosts: Host[];
    };
}

const initialValues: TestValues = {
    connectionMode: { id: ConnectionMode.secure },
    configuration: {
        agentInitiated: false,
        pollerInitiated: false,
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: [],
        hosts: []
    }
};

const initialValuesWithAgentEnabled: TestValues = {
    ...initialValues,
    configuration: {
        ...initialValues.configuration,
        agentInitiated: true
    }
};

const initialValuesWithPollerEnabled: TestValues = {
    connectionMode: { id: ConnectionMode.secure },
    configuration: {
        agentInitiated: false,
        pollerInitiated: true,
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: [],
        hosts: [{
            address: '',
            port: '',
            pollerCaCertificate: '',
            pollerCaName: '',
            token: null
        }]
    }
};

const initialize = (values: TestValues = initialValues): void => {
    const store = createStore();
    cy.mount({
        Component: (
            <Provider store={store}>
                <BrowserRouter>
                    <QueryClientProvider client={new QueryClient()}>
                        <Formik initialValues={values} onSubmit={cy.stub()}>
                            <ConnectionInitiated />
                        </Formik>
                    </QueryClientProvider>
                </BrowserRouter>
            </Provider>
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

        cy.contains('By agent').parent().find('svg[data-testid="DoneIcon"]').should('be.visible');
    });

    it('should show done icon when poller configuration is enabled', () => {
        initialize(initialValuesWithPollerEnabled);

        cy.contains('By poller').parent().find('svg[data-testid="DoneIcon"]').should('be.visible');
    });

    it('should enable agent initiated configuration', () => {
        initialize();

        cy.get('.MuiSwitch-root').click();
        cy.get('input[type="checkbox"]').should('be.checked');

        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('be.visible');
        cy.get('[data-testid="CA(.crt,.cer)"]').should('be.visible');
        cy.get('[data-testid="Private key(.key)"]').should('be.visible');
        cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');

        cy.makeSnapshot();
    });

    it('should disable agent initiated configuration', () => {
        initialize(initialValuesWithAgentEnabled);

        cy.get('.MuiSwitch-root').click();
        cy.get('input[type="checkbox"]').should('not.be.checked');

        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('not.exist');
    });

    it('should enable poller initiated configuration', () => {
        initialize();

        cy.contains('By poller').click();

        cy.get('.MuiSwitch-root').click();
        cy.get('input[type="checkbox"]').should('be.checked');

        cy.get('[role="tabpanel"][id*="poller"]').should('contain.text', 'Monitored hosts');
        cy.get('[role="tabpanel"][id*="poller"]').should('contain.text', 'Select host');
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
            connectionMode: { id: 'other-mode' as any },
            configuration: {
                agentInitiated: true,
                pollerInitiated: false,
                otelPublicCertificate: '',
                otelCaCertificate: '',
                otelPrivateKey: '',
                tokens: [],
                hosts: []
            }
        };

        initialize(valuesWithDifferentMode);

        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('not.exist');
    });

    it('should render AgentInitiated when connection mode is insecure', () => {
        const valuesWithInsecureMode: TestValues = {
            connectionMode: { id: ConnectionMode.insecure },
            configuration: {
                agentInitiated: true,
                pollerInitiated: false,
                otelPublicCertificate: '',
                otelCaCertificate: '',
                otelPrivateKey: '',
                tokens: [],
                hosts: []
            }
        };

        initialize(valuesWithInsecureMode);

        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('be.visible');
    });

    it('should maintain tab state when switching between tabs', () => {
        initialize();

        cy.get('.MuiSwitch-root').click();

        cy.contains('By poller').click();
        cy.get('[role="tabpanel"][id*="poller"]').should('be.visible');

        cy.contains('By agent').click();
        cy.get('[role="tabpanel"][id*="agent"]').should('be.visible');

        cy.get('input[type="checkbox"]').should('be.checked');
        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('be.visible');
    });
});
