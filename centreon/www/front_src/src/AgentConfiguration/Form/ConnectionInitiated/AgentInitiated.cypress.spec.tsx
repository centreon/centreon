import { Formik } from 'formik';
import AgentInitiated from './AgentInitiated';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router';

const initialValues = {
    configuration: {
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: [{ name: 'testName', id: 1, inputValue: 'testInput' }, { name: 'testName2', id: 2, inputValue: 'testInput2', testId: 'testId2' }],
    }
};

const initialize = (values = initialValues): void => {
    const store = createStore();
    cy.mount({
        Component: (
            <Provider store={store}>
                <BrowserRouter>
                    <QueryClientProvider client={new QueryClient()}>
                        <Formik initialValues={values} onSubmit={cy.stub()}>
                            <AgentInitiated />
                        </Formik>
                    </QueryClientProvider>
                </BrowserRouter>
            </Provider >
        )
    });
}

describe('AgentInitiated', () => {
    it('should render the component with initial values', () => {
        initialize();
        cy.get('[data-testid="Public certificate(.crt,.cer)"]').should('have.value', '');
        cy.get('[data-testid="CA(.crt,.cer)"').should('have.value', '');
        cy.get('[data-testid="Private key(.key)"]').should('have.value', '');
        cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');

        cy.makeSnapshot();
    });

    it('should update the public certificate field', () => {
        initialize();
        cy.get('[data-testid="Public certificate(.crt,.cer)"]').eq(0).click().type('test.crt');
        cy.get('[data-testid="Public certificate(.crt,.cer)"]').eq(1).should('have.value', 'test.crt');
    });

    it('should update the CA certificate field', () => {
        initialize();
        cy.get('[data-testid="CA(.crt,.cer)"]').eq(0).click().type('test_ca.crt');
        cy.get('[data-testid="CA(.crt,.cer)"]').eq(1).should('have.value', 'test_ca.crt');
    });

    it('should update the private key field', () => {
        initialize();
        cy.get('[data-testid="Private key(.key)"]').eq(0).click().type('test.key');
        cy.get('[data-testid="Private key(.key)"]').eq(1).should('have.value', 'test.key');
    });

    it('should update the tokens field', () => {
        initialize();
        cy.get('[data-testid="Select existing CMA token(s)"]').click();
        cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');
        cy.get('[id="«r1u»-option-0"]').click();
        cy.get('[data-testid="Select existing CMA token(s)"]').should('not.contain', 'testName');
    });
});
