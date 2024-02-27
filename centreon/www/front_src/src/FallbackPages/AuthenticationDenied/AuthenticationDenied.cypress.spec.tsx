import {
  labelAuthenticationDenied,
  labelYouAreNotAbleToLogIn
} from './translatedLabels';

import AuthenticationDenied from '.';

describe('Authentication denied', () => {
  beforeEach(() => {
    cy.mount({
      Component: <AuthenticationDenied />
    });
  });

  it('displays the authentication denied page', () => {
    cy.contains(labelYouAreNotAbleToLogIn).should('be.visible');
    cy.contains(labelAuthenticationDenied).should('be.visible');
  });
});
