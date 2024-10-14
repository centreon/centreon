


import FederatedComponentFallback from '../../federatedModules/Load/FederatedComponentFallback';
import FederatedPageFallback from '../../federatedModules/Load/FederatedPageFallback';
import { labelCannotLoadModule } from '../../federatedModules/translatedLabels';

const initializeFallback = (Fallback): void => {
  cy.mount({
    Component: <Fallback />
  });
};

describe('Fallback', () => {
  it('displays the fallback page', () => {
    initializeFallback(FederatedPageFallback);

    cy.contains(labelCannotLoadModule).should('be.visible');
  });

  it('displays the fallback component', () => {
    initializeFallback(FederatedComponentFallback);

    cy.contains(labelCannotLoadModule).should('be.visible');

    cy.makeSnapshot();
  });
});
