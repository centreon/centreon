import {
  ThemeMode,
  platformVersionsAtom,
  userAtom
} from '@centreon/ui-context';

import { renderHook } from '@testing-library/react';
import i18next from 'i18next';
import { Provider, createStore, useAtomValue } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { PlatformVersions } from '../api/models';
import About from './About';

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

const resourceLinks = [
  { label: 'Browse the docs', url: 'https://docs.centreon.com' },
  { label: 'Join The Watch', url: 'https://thewatch.centreon.com' },
  { label: 'Open the repository', url: 'https://github.com/centreon/centreon' },
  {
    label: 'Compare Edition licenses',
    url: 'https://www.centreon.com/pricing-centreon-infra-monitoring/'
  }
];

const buildStore = () => {
  const store = createStore();

  store.set(platformVersionsAtom, platformVersion);

  return store;
};

const mountComponent = (): void => {
  cy.viewport('ipad-mini', 'portrait');
  cy.mount({
    Component: (
      <Provider store={buildStore()}>
        <About />
      </Provider>
    )
  });
};

describe('About page', () => {
  beforeEach(() => {
    // Trans needs an i18next instance to resolve the tags embedded in the
    // labels. Without resources the keys are returned as-is, in English.
    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });

    cy.clock(new Date(2021, 1, 1).getTime());
    cy.document().then((doc) => doc.documentElement.classList.remove('dark'));
  });

  it('displays the about page', () => {
    mountComponent();

    cy.contains('23.04.0').should('be.visible');
    cy.findByLabelText('Star centreon/centreon on GitHub').should(
      'have.attr',
      'href',
      'https://github.com/centreon/centreon'
    );

    cy.contains('Project leaders').should('not.exist');
    cy.contains('See the full list on GitHub')
      .should('have.attr', 'href')
      .and('include', 'graphs/contributors');

    cy.contains('Report a vulnerability')
      .should('have.attr', 'href')
      .and('include', 'security/policy');

    resourceLinks.forEach(({ label, url }) => {
      cy.contains(label)
        .should('be.visible')
        .closest('a')
        .should('have.attr', 'href', url)
        .and('have.attr', 'target', '_blank');
    });

    cy.contains('Copyright © 2005 - 2021 Centreon').should('be.visible');

    cy.contains('23.04.0')
      .parent()
      .should('have.css', 'background-color', 'rgb(37, 88, 145)');
    cy.contains('Project & contributors').should(
      'have.css',
      'color',
      'rgb(0, 0, 0)'
    );

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

    cy.contains('23.04.0')
      .parent()
      .should('have.css', 'background-color', 'rgb(73, 116, 165)');

    cy.contains('Project & contributors').should(
      'have.css',
      'color',
      'rgb(255, 255, 255)'
    );

    cy.makeSnapshot();
  });
});
