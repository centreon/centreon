import { createStore } from 'jotai';

import { Method } from '..';

import LicensedModule from './LicensedModule';

import Module from '.';

const initializeModule = (): void => {
  cy.mount({
    Component: (
      <Module seedName="seed" store={createStore()}>
        <p>Module</p>
      </Module>
    )
  });
};

const initializeModuleWithValidLicense = (
  isFederatedComponent = false
): void => {
  cy.interceptAPIRequest({
    alias: 'getValidLicense',
    method: Method.GET,
    path: './api/internal.php?object=centreon_license_manager&action=licenseValid&productName=valid',
    response: {
      success: true
    }
  });

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <LicensedModule
          isFederatedComponent={isFederatedComponent}
          moduleName="valid"
          seedName="seed"
          store={createStore()}
        >
          <p>Module</p>
        </LicensedModule>
      </div>
    )
  });
};

const initializeModuleWithInvalidLicense = (
  isFederatedComponent = false
): void => {
  cy.interceptAPIRequest({
    alias: 'getInvalidLicense',
    method: Method.GET,
    path: './api/internal.php?object=centreon_license_manager&action=licenseValid&productName=invalid',
    response: {
      success: false
    }
  });

  cy.mount({
    Component: (
      <div style={{ height: '100vh' }}>
        <LicensedModule
          isFederatedComponent={isFederatedComponent}
          moduleName="invalid"
          seedName="seed"
          store={createStore()}
        >
          <p>Module</p>
        </LicensedModule>
      </div>
    )
  });
};

describe('Module', () => {
  beforeEach(() => {
    initializeModule();
  });

  it('displays the content of the module', () => {
    cy.contains('Module').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Valid license module', () => {
  it('displays the content of the page when the license is valid license', () => {
    initializeModuleWithValidLicense();
    cy.waitForRequest('@getValidLicense');

    cy.contains('Module').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the content of the component when the license is valid license', () => {
    initializeModuleWithValidLicense(true);

    cy.contains('Module').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Invalid license module', () => {
  it('displays the content of the page when the license is invalid license', () => {
    initializeModuleWithInvalidLicense();
    cy.waitForRequest('@getInvalidLicense');

    cy.contains('Module').should('not.exist');

    cy.contains('Oops').should('be.visible');
    cy.contains('License invalid or expired').should('be.visible');
    cy.contains('Please contact your administrator.').should('be.visible');
    cy.get('img[alt="License invalid or expired !"]').should('be.visible');
    cy.get('img[alt="Centreon Logo"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the content of the module when the license is invalid license', () => {
    initializeModuleWithInvalidLicense(true);

    cy.contains('Module').should('not.exist');

    cy.makeSnapshot();
  });
});
