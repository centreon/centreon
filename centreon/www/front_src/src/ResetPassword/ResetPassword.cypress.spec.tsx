import { BrowserRouter } from 'react-router-dom';
import { Provider } from 'jotai';
import { replace } from 'ramda';

import { SnackbarProvider } from '@centreon/ui';

import { labelCentreonLogo } from '../Login/translatedLabels';
import { loginEndpoint } from '../Login/api/endpoint';
import { Method } from '../../../../cypress/support/commands';
import { userEndpoint } from '../api/endpoint';

import { passwordResetInformationsAtom } from './passwordResetInformationsAtom';
import {
  labelCurrentPassword,
  labelNewPassword,
  labelNewPasswordConfirmation,
  labelNewPasswordsMustMatch,
  labelResetPassword,
  labelTheNewPasswordIstheSameAsTheOldPassword
} from './translatedLabels';
import { router } from './useResetPassword';

import ResetPassword from '.';

const retrievedUser = {
  alias: 'Admin',
  default_page: '/monitoring/resources',
  is_export_button_enabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false
};

const resetPasswordInitialValues = {
  alias: 'admin'
};

const retrievedLogin = {
  redirect_uri: '/monitoring/resources'
};

const mountComponentAndStub = (
  initialValues: unknown = resetPasswordInitialValues
): void => {
  const useNavigate = cy.stub();
  cy.stub(router, 'useNavigate').returns(useNavigate);

  cy.mount({
    Component: (
      <BrowserRouter>
        <SnackbarProvider>
          <Provider
            initialValues={[[passwordResetInformationsAtom, initialValues]]}
          >
            <ResetPassword />
          </Provider>
        </SnackbarProvider>
      </BrowserRouter>
    )
  });

  return useNavigate;
};

describe('Rest Password', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'resetPassword',
      method: Method.PUT,
      path: `**/authentication/users/admin/password`,
      response: {},
      statusCode: 201
    });

    cy.interceptAPIRequest({
      alias: 'providersConfiguration',
      method: Method.POST,
      path: replace('./api/latest', '**', loginEndpoint),
      response: retrievedLogin
    });

    cy.interceptAPIRequest({
      alias: 'getUser',
      method: Method.GET,
      path: replace('./api/latest', '**', userEndpoint),
      response: retrievedUser
    });
  });

  it('displays the reset password form', () => {
    mountComponentAndStub();
    cy.contains(labelResetPassword).should('be.visible');
    cy.findByLabelText(labelCurrentPassword).should('be.visible');
    cy.findByLabelText(labelNewPassword).should('be.visible');
    cy.findByLabelText(labelNewPasswordConfirmation).should('be.visible');
    cy.findByAltText(labelCentreonLogo).should('be.visible');
  });

  it('displays errors when the form is not correctly filled', () => {
    mountComponentAndStub();
    cy.findByLabelText(labelCurrentPassword).type('current-password');
    cy.findByLabelText(labelNewPassword).type('current-password');
    cy.findByLabelText(labelNewPasswordConfirmation).click();

    cy.contains(labelTheNewPasswordIstheSameAsTheOldPassword).should(
      'be.visible'
    );

    cy.findByLabelText(labelResetPassword).should('be.disabled');

    cy.findByLabelText(labelNewPassword).clear();
    cy.findByLabelText(labelNewPassword).type('new-password');
    cy.findByLabelText(labelNewPasswordConfirmation).type('new-password-2');
    cy.findByLabelText(labelNewPassword).click();

    cy.contains(labelNewPasswordsMustMatch).should('be.visible');

    cy.findByLabelText(labelResetPassword).should('be.disabled');
  });

  it('redirects the user back to the login page when the page does not have the required information', () => {
    const useNavigate = mountComponentAndStub(null);

    cy.findByAltText(labelCentreonLogo)
      .should('be.visible')
      .then(() => expect(useNavigate).to.be.calledWith('/login'));
  });

  it('redirects to the default page when the new password is successfully renewed', () => {
    const useNavigate = mountComponentAndStub();

    cy.findByLabelText(labelCurrentPassword).type('current-password');
    cy.findByLabelText(labelNewPassword).type('new-password');
    cy.findByLabelText(labelNewPasswordConfirmation).type('new-password');
    cy.findByLabelText(labelResetPassword).click();

    cy.waitForRequest('@resetPassword').then(({ request }) => {
      expect(request.body).to.equal(
        '{"new_password":"new-password","old_password":"current-password"}'
      );
    });

    cy.waitForRequest('@getUser');

    cy.waitForRequest('@providersConfiguration').then(() =>
      expect(useNavigate).to.be.calledWith('/monitoring/resources')
    );
  });
});
