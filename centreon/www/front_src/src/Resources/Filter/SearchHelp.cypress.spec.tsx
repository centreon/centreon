import { Provider, createStore } from 'jotai';

import { cloudDocsURL, getOnPremDocsURL } from '@centreon/ui';
import {
  platformFeaturesAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import {
  labelFindExplanationsAndExamples,
  labelHere,
  labelNeedHelpWithSearchBarUsage
} from '../translatedLabels';

import SearchHelp from './SearchHelp';

const platformVersions = {
  modules: {},
  web: {
    fix: '0',
    major: '23',
    minor: '10',
    version: '23.10.0'
  },
  widgets: {}
};
const platformFeatures = {
  featureFlags: {},
  isCloudPlatform: false
};

const store = createStore();
store.set(platformVersionsAtom, platformVersions);
store.set(platformFeaturesAtom, platformFeatures);

const SearchHelpWithProvider = (): JSX.Element => (
  <Provider store={store}>
    <SearchHelp />
  </Provider>
);

describe('Searchbar help tooltip', () => {
  beforeEach(() => {
    cy.mount({
      Component: <SearchHelpWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  it('displays a tooltip containing a documentation link upon clicking the help icon', () => {
    const docsURL = getOnPremDocsURL({
      majorVersion: '23',
      minorVersion: '10'
    });

    cy.findByLabelText('Search help').click();

    cy.findByText(labelNeedHelpWithSearchBarUsage);
    cy.findByText(labelFindExplanationsAndExamples);

    cy.findByText(labelHere).should('have.attr', 'href', docsURL);

    cy.makeSnapshot();
  });

  it('displays a tooltip containing a cloud documentation link upon clicking the help icon in a cloud environment', () => {
    store.set(platformFeaturesAtom, {
      ...platformFeaturesAtom,
      isCloudPlatform: true
    });

    cy.findByLabelText('Search help').click();

    cy.findByText(labelNeedHelpWithSearchBarUsage);
    cy.findByText(labelFindExplanationsAndExamples);

    cy.findByText(labelHere).should('have.attr', 'href', cloudDocsURL);

    cy.makeSnapshot();
  });
});
