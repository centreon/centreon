import {
  platformVersionsAtom,
  ThemeMode,
  userAtom
} from '@centreon/ui-context';

import { renderHook } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider, useAtomValue } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { PlatformVersions } from '../api/models';
import About from './About';
import { pendoSlotId } from './Sections/ResourcesGrid';

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

const buildStore = () => {
  const store = createStore();

  store.set(platformVersionsAtom, platformVersion);

  return store;
};

const layoutIds = [
  'about-page',
  'about-hero',
  'about-project-and-contributors',
  'about-security',
  'about-resources',
  'about-resources-grid',
  'about-resource-documentation',
  'about-resource-the-watch',
  'about-resource-github',
  'about-resource-editions',
  'about-footer',
  pendoSlotId
];

const injectPendoContent = (): void => {
  cy.get(`#${pendoSlotId}`).then(([slot]) => {
    const content = document.createElement('div');
    content.textContent = 'Pendo content';
    content.style.height = '120px';
    slot.appendChild(content);
  });
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

    // The dark mode test switches the theme on the shared user atom: reset it
    // so the following tests do not render dark text colors on a light page.
    const userData = renderHook(() => useAtomValue(userAtom));
    userData.result.current.themeMode = ThemeMode.light;
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

    cy.contains('Browse the docs').should('be.visible');
    cy.contains('Join The Watch').should('be.visible');
    cy.contains('Open the repository').should('be.visible');
    cy.contains('Compare Edition licenses').should('be.visible');
    cy.contains('Open source edition').should('not.exist');
    cy.contains('Start free trial').should('not.exist');

    cy.contains('Copyright © 2005 - 2021 Centreon').should('be.visible');

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

  describe('Pendo slot', () => {
    it('exposes a unique id on each layout block', () => {
      mountComponent();

      layoutIds.forEach((id) => {
        cy.get(`[id="${id}"]`).should('have.length', 1);
      });
    });

    it('hides the slot and keeps the resources grid full width while empty', () => {
      mountComponent();
      cy.viewport(1280, 800);

      cy.get(`#${pendoSlotId}`).should('not.be.visible');
      cy.get('#about-resources-grid').then(([grid]) => {
        cy.get('#about-resources')
          .invoke('width')
          .should('be.closeTo', grid.getBoundingClientRect().width, 1);
      });
    });

    it('shows the injected content on the right half of the section on wide screens', () => {
      mountComponent();
      cy.viewport(1280, 800);
      injectPendoContent();

      cy.contains('Pendo content').should('be.visible');
      cy.get('#about-resources-grid').then(([grid]) => {
        cy.get(`#${pendoSlotId}`).then(([slot]) => {
          const gridBox = grid.getBoundingClientRect();
          const slotBox = slot.getBoundingClientRect();
          const sectionWidth = Cypress.$('#about-resources').width() ?? 0;

          expect(slotBox.left).to.be.greaterThan(gridBox.right);
          expect(slotBox.top).to.be.closeTo(gridBox.top, 1);
          expect(slotBox.width).to.be.closeTo(sectionWidth / 2, 1);
        });
      });

      cy.makeSnapshot();
    });

    it('shows the injected content below the resources grid on narrow screens', () => {
      mountComponent();
      injectPendoContent();

      cy.contains('Pendo content').should('be.visible');
      cy.get('#about-resources-grid').then(([grid]) => {
        cy.get(`#${pendoSlotId}`).then(([slot]) => {
          expect(slot.getBoundingClientRect().top).to.be.greaterThan(
            grid.getBoundingClientRect().bottom
          );
        });
      });
    });
  });
});
