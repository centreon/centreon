import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { replace } from 'ramda';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { externalTranslationEndpoint } from '../App/endpoint';
import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import { areUserParametersLoadedAtom } from '../Main/useUser';
import { userEndpoint } from '../api/endpoint';

import {
  loginEndpoint,
  loginPageCustomisationEndpoint,
  providersConfigurationEndpoint
} from './api/endpoint';
import {
  labelAlias,
  labelAnErrorOccurredDuringAuthentication,
  labelCentreonLogo,
  labelCentreonWallpaper,
  labelConnect,
  labelDisplayThePassword,
  labelHideThePassword,
  labelLoginSucceeded,
  labelLoginWith,
  labelPassword,
  labelPasswordHasExpired,
  labelPoweredByCentreon,
  labelRequired
} from './translatedLabels';
import { router } from './useLogin';

import LoginPage from '.';

const labelInvalidCredentials = 'Invalid credentials';
const labelError = 'This is an error from the server';

const retrievedWeb = {
  modules: {},
  web: {
    version: '21.10.1'
  }
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

const retrievedTranslations = {
  en: {
    hello: 'Hello'
  }
};

const retrievedProvidersConfiguration = [
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/local',
    id: 1,
    is_active: true,
    name: 'local'
  },
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/openid',
    id: 2,
    is_active: true,
    name: 'openid'
  },
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/ldap',
    id: 3,
    is_active: false,
    name: 'ldap'
  }
];

const retrievedForcedProvidersConfiguration = [
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/local',
    id: 1,
    is_active: true,
    name: 'local'
  },
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/openid',
    id: 2,
    is_active: true,
    is_forced: true,
    name: 'openid'
  }
];

const retrievedUser = {
  alias: 'Admin alias',
  default_page: '/monitoring/resources',
  is_export_button_enabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false
};

const mockNow = new Date('2020-01-01');

const store = createStore();

store.set(areUserParametersLoadedAtom, false);
store.set(platformInstallationStatusAtom, {
  hasUpgradeAvailable: false,
  isInstalled: false
});
store.set(platformVersionsAtom, retrievedWeb);

const TestComponent = (): JSX.Element => (
  <BrowserRouter>
    <SnackbarProvider>
      <TestQueryProvider>
        <Provider store={store}>
          <LoginPage />
        </Provider>
      </TestQueryProvider>
    </SnackbarProvider>
  </BrowserRouter>
);

const mountComponentAndStubs = (): unknown => {
  const useNavigate = cy.stub();
  cy.stub(router, 'useNavigate').returns(useNavigate);

  cy.viewport('macbook-13');

  cy.mount({
    Component: <TestComponent />
  });

  return useNavigate;
};

const mockPostLogin = ({ response, statusCode = 200 }): void => {
  cy.interceptAPIRequest({
    alias: 'postLogin',
    method: Method.POST,
    path: `${replace('./', '**', loginEndpoint)}`,
    response,
    statusCode
  });
};

const mockPostLoginSuccess = (): void => {
  mockPostLogin({
    response: {
      redirect_uri: '/monitoring/resources'
    }
  });
};

const mockPostLoginInvalidCredentials = (): void => {
  mockPostLogin({
    response: { code: 401, message: labelInvalidCredentials },
    statusCode: 401
  });
};

const mockPostLoginPasswordExpired = (): void => {
  mockPostLogin({
    response: {
      password_is_expired: true,
      redirect_uri: '/monitoring/resources'
    },
    statusCode: 401
  });
};

const mockPostLoginServerError = (): void => {
  mockPostLogin({
    response: { message: labelError },
    statusCode: 500
  });
};

const setupBeforeEach = (): void => {
  cy.clock(mockNow);

  cy.interceptAPIRequest({
    alias: 'getTranslations',
    method: Method.GET,
    path: `${replace('./', '**', externalTranslationEndpoint)}`,
    response: retrievedTranslations
  });
  cy.interceptAPIRequest({
    alias: 'getProvidersConfiguration',
    method: Method.GET,
    path: `${replace('./', '**', providersConfigurationEndpoint)}`,
    response: retrievedProvidersConfiguration
  });
  cy.interceptAPIRequest({
    alias: 'getUser',
    method: Method.GET,
    path: `${replace('./', '**', userEndpoint)}`,
    response: retrievedUser
  });

  i18next.use(initReactI18next).init({
    fallbackLng: 'en',
    keySeparator: false,
    lng: 'en',
    nsSeparator: false,
    resources: {}
  });
};

describe('Login Page', () => {
  beforeEach(() => {
    setupBeforeEach();
  });

  it('displays the login page', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getTranslations');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');
    cy.findByLabelText(labelAlias).should('be.visible');
    cy.findByLabelText(labelPassword).should('be.visible');
    cy.findByLabelText(labelConnect).should('be.visible');
    cy.contains(labelPoweredByCentreon).should('be.visible');
    cy.contains('v. 21.10.1').should('be.visible');

    cy.findByTestId('Login with openid').should(
      'have.attr',
      'href',
      '/centreon/authentication/providers/configurations/openid'
    );

    cy.makeSnapshot();
  });

  it(`submits the credentials when they are valid and the "${labelConnect}" is clicked`, () => {
    mockPostLoginSuccess();
    const useNavigate = mountComponentAndStubs();

    cy.findByLabelText(labelAlias).type('admin');
    cy.findByLabelText(labelPassword).type('centreon');
    cy.findByLabelText(labelConnect).click();

    cy.waitForRequest('@postLogin').then(({ request }) => {
      expect(request.body).equal('{"login":"admin","password":"centreon"}');
    });

    cy.waitForRequest('@getUser');

    cy.contains(labelLoginSucceeded)
      .should('be.visible')
      .then(() => {
        expect(useNavigate).to.have.been.calledWith('/monitoring/resources');
      });
  });

  it(`does not submit the credentials when they are invalid and the "${labelConnect}" button is clicked`, () => {
    const useNavigate = mountComponentAndStubs();
    mockPostLoginInvalidCredentials();

    cy.waitForRequest('@getTranslations');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');

    cy.findByLabelText(labelAlias).type('invalid_alias');
    cy.findByLabelText(labelPassword).type('invalid_password');
    cy.findByLabelText(labelConnect).click();

    cy.waitForRequest('@postLogin').then(({ request }) => {
      expect(request.body).equal(
        '{"login":"invalid_alias","password":"invalid_password"}'
      );
    });

    cy.contains(labelInvalidCredentials)
      .should('be.visible')
      .then(() => {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        expect(useNavigate).to.not.have.been.called;
      });

    cy.makeSnapshot();
  });

  it('displays errors when fields are cleared', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getTranslations');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');

    cy.findByLabelText(labelConnect).should('be.disabled');

    cy.findByLabelText(labelAlias).type('admin');
    cy.findByLabelText(labelPassword).type('centreon');

    cy.findByLabelText(labelConnect).should('be.enabled');

    cy.findByLabelText(labelAlias).clear();
    cy.findByLabelText(labelPassword).clear();

    cy.findByLabelText(labelConnect).should('be.disabled');

    cy.findAllByText(labelRequired).should('have.length', 2);

    cy.makeSnapshot();
  });

  it('displays the password when the corresponding action is clicked', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getTranslations');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');

    cy.findByLabelText(labelPassword).type('password');

    cy.findByLabelText(labelPassword).should('have.attr', 'type', 'password');

    cy.findByLabelText(labelDisplayThePassword).click();

    cy.findByLabelText(labelPassword).should('have.attr', 'type', 'text');

    cy.findByLabelText(labelHideThePassword).click();

    cy.findByLabelText(labelPassword).should('have.attr', 'type', 'password');

    cy.makeSnapshot();
  });

  it('redirects to the reset page when the submitted password is expired', () => {
    mockPostLoginPasswordExpired();
    const useNavigate = mountComponentAndStubs();

    cy.findByLabelText(labelAlias).type('admin');
    cy.findByLabelText(labelPassword).type('centreon');
    cy.findByLabelText(labelConnect).click();

    cy.waitForRequest('@postLogin');

    cy.contains(labelPasswordHasExpired)
      .should('be.visible')
      .then(() => {
        expect(useNavigate).to.have.been.calledWith('/reset-password');
      });
  });

  it('stays on the login page when the login request returns a 500 error', () => {
    const useNavigate = mountComponentAndStubs();
    mockPostLoginServerError();

    cy.waitForRequest('@getTranslations');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');

    cy.findByLabelText(labelAlias).type('admin');
    cy.findByLabelText(labelPassword).type('centreon');
    cy.findByLabelText(labelConnect).click();

    cy.waitForRequest('@postLogin');

    cy.contains(labelError)
      .should('be.visible')
      .then(() => {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        expect(useNavigate).to.not.have.been.called;
      });

    cy.findByLabelText(labelAlias).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a fallback page when a provider is forced and an error occurred during authentication', () => {
    mountComponentAndStubs();
    cy.stub(router, 'useSearchParams').returns([
      new Map([['authenticationError', 'An error occurred']])
    ]);
    cy.interceptAPIRequest({
      alias: 'getProvidersConfiguration',
      method: Method.GET,
      path: `${replace('./', '**', providersConfigurationEndpoint)}`,
      response: retrievedForcedProvidersConfiguration
    });

    cy.waitForRequest('@getProvidersConfiguration');

    cy.contains(labelAnErrorOccurredDuringAuthentication).should('be.visible');
    cy.contains('An error occurred').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Default custom login page', () => {
  beforeEach(() => {
    setupBeforeEach();
    store.set(platformVersionsAtom, retrievedWebWithItEditionInstalled);
    cy.fixture('login/defaultLoginPageCustomization.json').then((fixture) =>
      cy.interceptAPIRequest({
        alias: 'getDefaultLoginCustomization',
        method: Method.GET,
        path: `${replace('./', '**', loginPageCustomisationEndpoint)}`,
        response: fixture
      })
    );
  });

  it('displays the login page with default custom login data when the IT edition extensions module is installed', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getDefaultLoginCustomization');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');
    cy.findByLabelText(labelAlias).should('be.visible');
    cy.findByLabelText(labelPassword).should('be.visible');
    cy.findByLabelText(labelConnect).should('be.visible');
    cy.contains(labelPoweredByCentreon).should('be.visible');
    cy.contains('v. 21.10.1').should('be.visible');
    cy.findByLabelText(`${labelLoginWith} openid`).should(
      'have.attr',
      'href',
      '/centreon/authentication/providers/configurations/openid'
    );

    cy.get('#loginHeader').children().should('have.length', 1);
    cy.get('#Previewtop').should('not.exist');
    cy.get('#Previewbottom').should('not.exist');

    cy.makeSnapshot();
  });
});

describe('Custom login page with data', () => {
  beforeEach(() => {
    setupBeforeEach();
    store.set(platformVersionsAtom, retrievedWebWithItEditionInstalled);
    cy.fixture('login/loginPageCustomization.json').then((fixture) =>
      cy.interceptAPIRequest({
        alias: 'getLoginCustomization',
        method: Method.GET,
        path: `${replace('./', '**', loginPageCustomisationEndpoint)}`,
        response: fixture
      })
    );
  });

  it('displays the login page when it is customized', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getLoginCustomization');
    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByTestId(labelCentreonLogo).should('be.visible');
    cy.findByTestId(labelCentreonWallpaper).should('be.visible');
    cy.findByLabelText(labelAlias).should('be.visible');
    cy.findByLabelText(labelPassword).should('be.visible');
    cy.findByLabelText(labelConnect).should('be.visible');
    cy.contains(labelPoweredByCentreon).should('be.visible');
    cy.contains('v. 21.10.1').should('be.visible');
    cy.findByLabelText(`${labelLoginWith} openid`).should(
      'have.attr',
      'href',
      '/centreon/authentication/providers/configurations/openid'
    );

    cy.get('#loginHeader').children().should('have.length', 2);
    cy.findByText('Gendarmerie de la Haute-Garonne').should('be.visible');
    cy.get('#Previewtop').should('not.exist');
    cy.findByLabelText('Previewbottom')
      .should('be.visible')
      .contains('centreon');

    cy.makeSnapshot();
  });
});

describe('Login page without module it edition extensions installed', () => {
  beforeEach(() => {
    setupBeforeEach();
  });

  it('displays the login page when the IT edition extensions module is not installed', () => {
    mountComponentAndStubs();

    cy.waitForRequest('@getProvidersConfiguration');

    cy.findByAltText(labelCentreonLogo).should('be.visible');
    cy.findByAltText(labelCentreonWallpaper).should('be.visible');
    cy.findByLabelText(labelAlias).should('be.visible');
    cy.findByLabelText(labelPassword).should('be.visible');
    cy.findByLabelText(labelConnect).should('be.visible');
    cy.contains(labelPoweredByCentreon).should('be.visible');
    cy.contains('v. 21.10.1').should('be.visible');
    cy.findByLabelText(`${labelLoginWith} openid`).should(
      'have.attr',
      'href',
      '/centreon/authentication/providers/configurations/openid'
    );

    cy.get('#loginHeader').children().should('have.length', 1);
    cy.get('#Previewtop').should('not.exist');
    cy.get('#Previewbottom').should('not.exist');

    cy.makeSnapshot();
  });
});
