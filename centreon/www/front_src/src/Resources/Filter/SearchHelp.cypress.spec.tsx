import { Provider, createStore } from 'jotai';

import { cloudDocsURL, getOnPremDocsURL } from '@centreon/ui';

import {
  labelNeedHelpWithSearchBarUsage,
  labelFindExplanationsAndExamples,
  labelHere
} from '../translatedLabels';
import { platformVersionsAtom } from '../../Main/atoms/platformVersionsAtom';

import SearchHelp from './SearchHelp';

const platformVersions = {
  isCloudPlatform: false,
  modules: {},
  web: {
    fix: '0',
    major: '23',
    minor: '10',
    version: '23.10.0'
  },
  widgets: {}
};

const store = createStore();
store.set(platformVersionsAtom, platformVersions);

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

  it('displays a tooltip containing a documentation link upon help icon click', () => {
    const docsURL = getOnPremDocsURL({
      majorVersion: '23',
      minorVersion: '10'
    });

    cy.findByLabelText('Search help').click();

    cy.findByText(labelNeedHelpWithSearchBarUsage);
    cy.findByText(labelFindExplanationsAndExamples);

    cy.findByText(labelHere).should('have.attr', 'href', docsURL);

    cy.matchImageSnapshot();
  });

  it('displays a tooltip containing a cloud documentation link upon help icon click in a cloud environement', () => {
    store.set(platformVersionsAtom, {
      ...platformVersions,
      isCloudPlatform: true
    });

    cy.findByLabelText('Search help').click();

    cy.findByText(labelNeedHelpWithSearchBarUsage);
    cy.findByText(labelFindExplanationsAndExamples);

    cy.findByText(labelHere).should('have.attr', 'href', cloudDocsURL);

    cy.matchImageSnapshot();
  });
});
