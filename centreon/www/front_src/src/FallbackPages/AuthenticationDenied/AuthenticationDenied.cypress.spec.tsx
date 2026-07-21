import AuthenticationDenied from '.';
import {
  labelAuthenticationDenied,
  labelYouAreNotAbleToLogIn
} from './translatedLabels';

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
