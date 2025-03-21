import { equals } from 'ramda';

import initialize from './initialize';
import { getGroups, getPayload } from './utils';

import {
  labelAlias,
  labelApplyResourceAccessRule,
  labelComment,
  labelGeographicCoordinates,
  labelIcon,
  labelInvalidCoordinateFormat,
  labelName,
  labelSelectHosts
} from '../translatedLabels';

const platforms = ['OnPrem', 'Cloud'];

export default () => {
  describe('Modal', () => {
    platforms.forEach((platform) => {
      const isCloudPlatform = equals(platform, 'Cloud');

      it('displays the modal in view mode when the user does not have write access', () => {
        initialize({ isCloudPlatform, hasWriteAccess: false });

        cy.waitForRequest('@getAllHostGroups');

        cy.contains('host group 1').click();

        cy.waitForRequest('@getHostGroupDetails');

        cy.contains('View a host group').should('be.visible');

        cy.findAllByTestId(labelComment).eq(1).scrollIntoView();

        cy.makeSnapshot(
          `${platform} - displays the modal in view mode when the user does not have write access`
        );

        cy.findByLabelText('close').click();
      });
    });

    platforms.forEach((platform) => {
      describe(platform, () => {
        const isCloudPlatform = equals(platform, 'Cloud');

        beforeEach(() => initialize({ isCloudPlatform }));

        it('shows form fields organized into groups, with each field initialized with default values', () => {
          cy.waitForRequest('@getAllHostGroups');

          cy.get(`[data-testid="add-resource"]`).click();

          getGroups({ isCloudPlatform }).forEach(({ name }) => {
            cy.contains(name);
          });

          cy.findAllByTestId(labelName).eq(1).should('have.value', '');
          cy.findAllByTestId(labelAlias).eq(1).should('have.value', '');
          cy.findByTestId(labelSelectHosts).should('have.value', '');

          if (isCloudPlatform) {
            cy.findByTestId(labelApplyResourceAccessRule).should(
              'have.value',
              ''
            );
          } else {
            cy.findByTestId(labelApplyResourceAccessRule).should('not.exist');
          }

          cy.findAllByTestId(labelGeographicCoordinates)
            .eq(1)
            .should('have.value', '');
          cy.findAllByTestId(labelComment).eq(1).should('have.value', '');

          cy.makeSnapshot(
            `${platform}: shows form fields organized into groups, with each field initialized with default values`
          );

          cy.findByLabelText('close').click();
        });

        it('shows form fields organized into groups, with each field initialized with the value received from the API', () => {
          cy.waitForRequest('@getAllHostGroups');

          cy.contains('host group 1').click();

          cy.waitForRequest('@getHostGroupDetails');

          getGroups({ isCloudPlatform }).forEach(({ name }) => {
            cy.contains(name);
          });

          cy.findAllByTestId(labelName)
            .eq(1)
            .should('have.value', getPayload({}).name);
          cy.findAllByTestId(labelAlias)
            .eq(1)
            .should('have.value', getPayload({}).alias);

          cy.findByText('host 1').should('be.visible');
          cy.findByText('host 2').should('be.visible');
          cy.findByText('host 3').should('be.visible');

          if (isCloudPlatform) {
            cy.findByTestId(labelApplyResourceAccessRule).should('be.visible');

            cy.findByText('rule 1').should('be.visible');
            cy.findByText('rule 2').should('be.visible');
          } else {
            cy.findByTestId(labelApplyResourceAccessRule).should('not.exist');
          }

          cy.findAllByTestId(labelGeographicCoordinates)
            .eq(1)
            .should('have.value', getPayload({}).geo_coords);
          cy.findAllByTestId(labelComment)
            .eq(1)
            .should('have.value', getPayload({}).comment);

          cy.makeSnapshot(
            `${platform}: shows form fields organized into groups, with each field initialized with the value received from the API`
          );

          cy.findByLabelText('close').click();
        });

        it('sends a POST request when the Create Button is clicked', () => {
          cy.waitForRequest('@getAllHostGroups');

          cy.get(`[data-testid="add-resource"]`).click();

          cy.findAllByTestId(labelName).eq(1).clear().type(getPayload({}).name);
          cy.findAllByTestId(labelAlias)
            .eq(1)
            .clear()
            .type(getPayload({}).alias);
          cy.findAllByTestId(labelComment)
            .eq(1)
            .clear()
            .type(getPayload({ isCloudPlatform }).comment);
          cy.findAllByTestId(labelGeographicCoordinates)
            .eq(1)
            .clear()
            .type(getPayload({ isCloudPlatform }).geo_coords);

          cy.findByTestId(labelSelectHosts).click();

          cy.waitForRequest('@getHosts');
          cy.contains('host 1').click();
          cy.contains('host 2').click();
          cy.contains('host 3').click();

          cy.findByTestId('Modal-header').click();

          if (isCloudPlatform) {
            cy.findByTestId(labelApplyResourceAccessRule).click();

            cy.waitForRequest('@getAccessRules');

            cy.contains('rule 1').click();
            cy.contains('rule 2').click();

            cy.findByTestId('Modal-header').click();
          }

          cy.findByTestId(labelIcon).click();
          cy.waitForRequest('@getImagesList');
          cy.contains('cypress_logo').click();

          cy.get(`button[data-testid="submit"`).click();

          cy.waitForRequest('@createHostGroup').then(({ request }) => {
            expect(request.body).to.deep.equals(
              getPayload({ isCloudPlatform })
            );
          });

          cy.makeSnapshot(
            `${platform}: sends a POST request when the Create Button is clicked`
          );
        });

        it('sends an UPDATE request when the Update Button is clicked', () => {
          cy.waitForRequest('@getAllHostGroups');

          cy.contains('host group 1').click();

          cy.waitForRequest('@getHostGroupDetails');

          cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');

          cy.get(`button[data-testid="submit"`).click();

          cy.waitForRequest('@updateHostGroup').then(({ request }) => {
            expect(request.body).to.deep.equals({
              ...getPayload({ isCloudPlatform }),
              name: 'Updated name'
            });
          });

          cy.contains('Host group updated');

          cy.makeSnapshot(
            `${platform}: sends an UPDATE request when the Update Button is clicked`
          );
        });
      });
    });

    it('validate geographic coordianates', () => {
      initialize({});

      cy.waitForRequest('@getAllHostGroups');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findAllByTestId(labelName).eq(1).clear().type('name');
      cy.findAllByTestId(labelGeographicCoordinates).eq(1).clear().type('123');
      cy.findByTestId('Modal-header').click();

      cy.contains(labelInvalidCoordinateFormat);
      cy.get(`button[data-testid="submit"`).should('be.disabled');

      cy.makeSnapshot('validate geographic coordianates with wrong value');

      cy.findAllByTestId(labelGeographicCoordinates)
        .eq(1)
        .clear()
        .type('-40.12,22.44');

      cy.findByText(labelInvalidCoordinateFormat).should('not.exist');
      cy.get(`button[data-testid="submit"`).should('not.be.disabled');

      cy.makeSnapshot('validate geographic coordianates with correct value');

      cy.findByLabelText('close').click();
      cy.findByLabelText('Discard').click();
    });
  });
};
