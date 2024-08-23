import { renderHook } from '@testing-library/react';
import { Provider, createStore, useAtomValue } from 'jotai';

import {
  ThemeMode,
  platformVersionsAtom,
  userAtom
} from '@centreon/ui-context';

import { PlatformVersions } from '../api/models';

import About from './About';
import { contributors } from './Sections/Contibutors';
import { developers } from './Sections/Developers';
import { projectLeaders } from './Sections/ProjectLeaders';
import {
  labelCentreonWebsite,
  labelCentreonsGithub,
  labelCommunity
} from './translatedLabels';

const externalLinks = [
  {
    label: labelCentreonWebsite,
    url: 'https://www.centreon.com'
  },
  {
    label: labelCommunity,
    url: 'https://thewatch.centreon.com/'
  },
  {
    label: labelCentreonsGithub,
    url: 'https://github.com/centreon/centreon/graphs/contributors'
  }
];

const platformVersion: PlatformVersions = {
  modules: {},
  web: {
    version: '23.04.0'
  }
};

const store = createStore();

store.set(platformVersionsAtom, platformVersion);

const mountComponent = (): void => {
  cy.viewport('ipad-mini', 'portrait');
  cy.mount({
    Component: (
      <Provider store={store}>
        <About />
      </Provider>
    )
  });
};

describe('About page', () => {
  beforeEach(() => {
    cy.clock(new Date(2021, 1, 1).getTime());
  });

  it('displays the about page', () => {
    mountComponent();
    cy.findByAltText('Centreon Logo').should('be.visible');

    projectLeaders.forEach((project) => {
      cy.findByText(project).should('be.visible');
    });
    developers.forEach((developer) => {
      cy.findByText(developer).should('be.visible');
    });
    contributors.forEach((contributor) => {
      cy.findByText(contributor).should('be.visible');
    });

    externalLinks.forEach(({ label, url }) => {
      cy.findByLabelText(label).should('have.attr', 'href', url);
      cy.findByLabelText(label).should('have.attr', 'target', '_blank');
    });

    cy.contains('Copyright © 2005 - 2021').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the about page in dark mode', () => {
    const userData = renderHook(() => useAtomValue(userAtom));
    userData.result.current.themeMode = ThemeMode.dark;

    mountComponent();

    contributors.forEach((contributor) => {
      cy.findByText(contributor).should('be.visible');
    });

    cy.contains('Copyright © 2005 - 2021').should('exist');

    cy.makeSnapshot();
  });
});
