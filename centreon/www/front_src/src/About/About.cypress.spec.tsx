import {
  platformVersionsAtom,
  ThemeMode,
  userAtom
} from '@centreon/ui-context';

import { renderHook } from '@testing-library/react';
import { createStore, Provider, useAtomValue } from 'jotai';

import { PlatformVersions } from '../api/models';
import About from './About';
import { projectLeaders } from './data';
import { links } from './links';
import {
  labelBrowseTheDocs,
  labelCompareEditions,
  labelContributeOnGitHub,
  labelDocumentationAndGuides,
  labelEditionsAndCloud,
  labelGetMoreFromCentreon,
  labelInfraMonitoring,
  labelJoinTheWatch,
  labelOpenSourceEdition,
  labelReportAVulnerability,
  labelScalingBeyondOpenSource,
  labelSeeTheFullListOnGitHub,
  labelStartFreeTrial,
  labelTagline,
  labelTheWatchCommunity
} from './translatedLabels';

const externalLinks = [
  { label: labelSeeTheFullListOnGitHub, url: links.githubContributors },
  { label: labelReportAVulnerability, url: links.reportVulnerability }
];

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

const store = createStore();

store.set(platformVersionsAtom, platformVersion);

const mountComponent = (): void => {
  cy.viewport(1024, 768);
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

    cy.contains(labelInfraMonitoring).should('be.visible');
    cy.contains('23.04.0').should('be.visible');
    cy.contains(labelOpenSourceEdition).should('be.visible');
    cy.contains(labelTagline).should('be.visible');

    projectLeaders.forEach((leader) => {
      cy.findByText(leader).should('be.visible');
    });

    cy.contains(labelGetMoreFromCentreon).should('be.visible');
    [
      labelDocumentationAndGuides,
      labelTheWatchCommunity,
      labelContributeOnGitHub,
      labelEditionsAndCloud
    ].forEach((title) => {
      cy.contains(title).should('be.visible');
    });
    [labelBrowseTheDocs, labelJoinTheWatch, labelCompareEditions].forEach(
      (cta) => {
        cy.contains(cta).should('be.visible');
      }
    );

    cy.contains(labelScalingBeyondOpenSource).should('be.visible');
    cy.contains(labelStartFreeTrial).should('be.visible');

    externalLinks.forEach(({ label, url }) => {
      cy.findByLabelText(label).should('have.attr', 'href', url);
      cy.findByLabelText(label).should('have.attr', 'target', '_blank');
    });

    cy.contains('Copyright © 2005 – 2021 Centreon').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the about page in dark mode', () => {
    const userData = renderHook(() => useAtomValue(userAtom));
    userData.result.current.themeMode = ThemeMode.dark;

    mountComponent();

    cy.contains(labelGetMoreFromCentreon).should('be.visible');
    cy.contains('Copyright © 2005 – 2021 Centreon').should('exist');

    cy.makeSnapshot();
  });
});
