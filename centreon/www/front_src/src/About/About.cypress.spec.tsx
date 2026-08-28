import {
  platformFeaturesAtom,
  platformVersionsAtom,
  ThemeMode,
  userAtom
} from '@centreon/ui-context';

import { renderHook } from '@testing-library/react';
import { createStore, Provider, useAtomValue } from 'jotai';

import { PlatformVersions } from '../api/models';
import About from './About';
import { projectLeaders } from './Sections/Credits';

const platformVersion: PlatformVersions = {
  modules: {},
  web: {
    fix: '0',
    major: '23',
    minor: '04',
    version: '23.04.0'
  },
  widgets: {}
};

const buildStore = (isCloudPlatform: boolean) => {
  const store = createStore();

  store.set(platformVersionsAtom, platformVersion);
  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform
  });

  return store;
};

const mountComponent = ({
  isCloudPlatform = false
}: {
  isCloudPlatform?: boolean;
} = {}): void => {
  cy.viewport('ipad-mini', 'portrait');
  cy.mount({
    Component: (
      <Provider store={buildStore(isCloudPlatform)}>
        <About />
      </Provider>
    )
  });
};

describe('About page', () => {
  beforeEach(() => {
    cy.clock(new Date(2021, 1, 1).getTime());
    cy.document().then((doc) => doc.documentElement.classList.remove('dark'));
  });

  it('displays the about page', () => {
    mountComponent();

    cy.contains('23.04.0').should('be.visible');
    cy.contains('Open source edition').should('be.visible');
    cy.findByLabelText('Star centreon/centreon on GitHub').should(
      'have.attr',
      'href',
      'https://github.com/centreon/centreon'
    );

    projectLeaders.forEach((leader) => {
      cy.contains(leader).should('be.visible');
    });

    cy.contains('Report a vulnerability')
      .should('have.attr', 'href')
      .and('include', 'security/policy');

    cy.contains('Browse the docs').should('be.visible');
    cy.contains('Join TheWatch').should('be.visible');
    cy.contains('Open the repository').should('be.visible');
    cy.contains('Compare editions').should('be.visible');
    cy.contains('Start free trial').should('be.visible');

    cy.contains('Copyright © 2005 - 2021 Centreon').should('be.visible');

    cy.makeSnapshot();
  });

  it('hides the open source edition tag and the editions upsell for Cloud platforms', () => {
    mountComponent({ isCloudPlatform: true });

    cy.contains('Open source edition').should('not.exist');
    cy.contains('Start free trial').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the about page in dark mode', () => {
    const userData = renderHook(() => useAtomValue(userAtom));
    userData.result.current.themeMode = ThemeMode.dark;

    // The application mirrors the theme mode onto the root element so that the
    // Tailwind `dark` variant applies. See Main/useUser.ts.
    cy.document().then((doc) => doc.documentElement.classList.add('dark'));

    mountComponent();

    cy.contains('23.04.0').should('be.visible');
    cy.contains('Copyright © 2005 - 2021 Centreon').should('exist');

    cy.contains('Project & contributors').should(
      'have.css',
      'color',
      'rgb(255, 255, 255)'
    );

    cy.makeSnapshot();
  });
});
