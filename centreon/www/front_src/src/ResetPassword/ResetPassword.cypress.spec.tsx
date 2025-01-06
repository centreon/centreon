import { Provider, createStore } from 'jotai';
import { replace } from 'ramda';
import { BrowserRouter } from 'react-router';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import {
  loginEndpoint,
  loginPageCustomisationEndpoint
} from '../Login/api/endpoint';
import {
  labelCentreonLogo,
  labelCentreonWallpaper,
  labelPoweredByCentreon
} from '../Login/translatedLabels';
import { userEndpoint } from '../api/endpoint';

import { platformVersionsAtom } from '@centreon/ui-context';
import ResetPassword from '.';
import {
  PasswordResetInformations,
  passwordResetInformationsAtom
} from './passwordResetInformationsAtom';
import {
  labelCurrentPassword,
  labelNewPassword,
  labelNewPasswordConfirmation,
  labelNewPasswordsMustMatch,
  labelResetPassword,
  labelTheNewPasswordIstheSameAsTheOldPassword
} from './translatedLabels';
import { router } from './useResetPassword';

const retrievedUser = {
  alias: 'Admin',
  default_page: '/monitoring/resources',
  isExportButtonEnabled: true,
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

const retrievedWebWithItEditionInstalled = {
  modules: {
    'centreon-it-edition-extensions': {
      fix: '0',
      major: '23',
      minor: '10',
      version: '23.10.0'
    }
  },
  web: {
    fix: '1',
    major: '21',
    minor: '10',
    version: '21.10.1'
  }
};

const interceptAPIRequests = () => {
  cy.interceptAPIRequest({
    alias: 'resetPassword',
    method: Method.PUT,
    path: '**/authentication/users/admin/password',
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

  cy.fixture('login/loginPageCustomization.json').then((fixture) =>
    cy.interceptAPIRequest({
      alias: 'getLoginCustomization',
      method: Method.GET,
      path: `${replace('./', '**', loginPageCustomisationEndpoint)}`,
      response: fixture
    })
  );
};

const mountComponentAndStub = ({
  initialValues = resetPasswordInitialValues,
  hasItEditionInstalled = false
}: {
  initialValues?: PasswordResetInformations | null;
  hasItEditionInstalled?: boolean;
}): unknown => {
  const store = createStore();

  store.set(passwordResetInformationsAtom, initialValues);

  if (hasItEditionInstalled) {
    store.set(platformVersionsAtom, retrievedWebWithItEditionInstalled);
  }

  const useNavigate = cy.stub();
  cy.stub(router, 'useNavigate').returns(useNavigate);

  cy.mount({
    Component: (
      <BrowserRouter>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <ResetPassword />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </BrowserRouter>
    )
  });

  return useNavigate;
};

describe('Reset Password', () => {
  beforeEach(interceptAPIRequests);

  it('displays the reset password form', () => {
    mountComponentAndStub({});

    cy.contains(labelResetPassword).should('be.visible');
    cy.findByLabelText(labelCurrentPassword).should('be.visible');
    cy.findByLabelText(labelNewPassword).should('be.visible');
    cy.findByLabelText(labelNewPasswordConfirmation).should('be.visible');
    cy.findByAltText(labelCentreonLogo).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays errors when the form is not correctly filled', () => {
    mountComponentAndStub({});
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

    cy.makeSnapshot();
  });

  it('redirects the user back to the login page when the page does not have the required information', () => {
    const useNavigate = mountComponentAndStub({ initialValues: null });

    cy.findByAltText(labelCentreonLogo)
      .should('be.visible')
      .then(() => expect(useNavigate).to.be.calledWith('/login'));
  });

  it('redirects to the default page when the new password is successfully renewed', () => {
    const useNavigate = mountComponentAndStub({});

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

describe('Custom Reset Password page', () => {
  beforeEach(() => {
    interceptAPIRequests();
  });

  it('displays the Reset Password page when it is customized', () => {
    mountComponentAndStub({ hasItEditionInstalled: true });

    cy.waitForRequest('@getLoginCustomization');

    cy.findByTestId(labelCentreonLogo).should('be.visible');
    cy.findByTestId(labelCentreonWallpaper).should('be.visible');
    cy.contains(labelPoweredByCentreon).should('be.visible');
    cy.contains('v. 21.10.1').should('be.visible');

    cy.findByText('Gendarmerie de la Haute-Garonne').should('be.visible');
    cy.get('#Previewtop').should('not.exist');
    cy.findByLabelText('Previewbottom')
      .should('be.visible')
      .contains('centreon');

    cy.makeSnapshot();
  });
});
