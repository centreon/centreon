import initialize from './initialize';

import { capitalize } from '@mui/material';
import { equals } from 'ramda';

import {
  labelDisabledHosts,
  labelEnabledHosts,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from '../translatedLabels';

export default () => {
  describe('Listing: ', () => {
    it('hides all actions and action columns when the user does not have write access', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHostGroups');
      cy.contains('host group 0');

      cy.makeSnapshot();
    });

    it('renders the Host group page with the ConfigurationBase layout', () => {
      initialize({});

      cy.waitForRequest('@getAllHostGroups');

      cy.contains('Host groups').should('be.visible');

      cy.makeSnapshot();
    });

    ['name', 'alias'].forEach((column) => {
      it(`sorts the ${column} column when clicked`, () => {
        initialize({});

        cy.waitForRequest('@getAllHostGroups');

        cy.contains(capitalize(column)).click();

        cy.waitForRequest('@getAllHostGroups').then(({ request }) => {
          expect(
            JSON.parse(request.url.searchParams.get('sort_by'))
          ).to.deep.equal({
            [column]: 'desc'
          });
        });
      });
    });

    it('truncates the name and alias fields when their length exceeds 50 characters', () => {
      initialize({});

      cy.contains(`${'hostGroup0'.repeat(5)}...`).should('be.visible');
      cy.contains(`${'alias'.repeat(10)}...`).should('be.visible');
    });

    ['enabled host groups', 'disabled host groups'].forEach((column, i) => {
      const isEnabledHost = equals(i, 0);

      it(`displays all hosts of the host group when hovering over the ${column} column`, () => {
        initialize({});

        cy.waitForRequest('@getAllHostGroups');

        cy.findByText(isEnabledHost ? '12' : '15').trigger('mouseover');

        cy.contains(
          isEnabledHost ? labelEnabledHosts : labelDisabledHosts
        ).should('be.visible');
        cy.contains('host 1').should('be.visible');

        cy.makeSnapshot();
      });

      it(`displays a 'Not found' message when hovering over the ${column} column with no hosts`, () => {
        initialize({ isEmptyHostGroup: true });

        cy.waitForRequest('@getAllHostGroups');

        cy.findAllByText('0')
          .eq(isEnabledHost ? 8 : 7)
          .trigger('mouseover');

        cy.contains(
          isEnabledHost ? labelNoEnabledHosts : labelNoDisabledHosts
        ).should('be.visible');

        cy.makeSnapshot();
      });
    });
  });
};
