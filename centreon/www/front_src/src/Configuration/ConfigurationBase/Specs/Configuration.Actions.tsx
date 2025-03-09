import { capitalize } from '@mui/material';
import pluralize from 'pluralize';

import initialize, { mockActionsRequests } from './initialize';

import {
  getLabelDeleteMany,
  getLabelDeleteOne,
  getLabelDuplicateMany,
  getLabelDuplicateOne
} from './utils';

import {
  labelDelete,
  labelDeleteResource,
  labelDisable,
  labelDuplicate,
  labelDuplicateResource,
  labelDuplications,
  labelEnable,
  labelEnableDisable,
  labelMoreActions,
  labelResourceDeleted,
  labelResourceDisabled,
  labelResourceDuplicated,
  labelResourceEnabled
} from '../translatedLabels';

export default (resourceType) => {
  describe('Actions: ', () => {
    beforeEach(() => {
      initialize({ resourceType });
      mockActionsRequests(resourceType.replace(' ', '_'));
    });

    it('enables the more actions button when selecting resources', () => {
      cy.waitForRequest('@getAll');

      cy.findAllByTestId(labelMoreActions).eq(0).should('be.disabled');

      cy.findByLabelText('Select row 1').click();
      cy.findByLabelText('Select row 2').click({ force: true });
      cy.findByLabelText('Select row 3').click();

      cy.findAllByTestId(labelMoreActions).eq(0).should('not.be.disabled');
    });

    describe('Delete', () => {
      it('displays the confirmation dialog when the inline delete button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelDelete}_1`).click();

        cy.contains(labelDeleteResource(resourceType));
        cy.contains(
          getLabelDeleteOne(resourceType, `${resourceType.replace(' ', '_')} 1`)
        );

        cy.makeSnapshot(
          `${resourceType}: displays the confirmation dialog when the inline delete button is clicked`
        );
      });

      it('displays the confirmation dialog when the massive delete button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();

        cy.findAllByTestId(labelDelete).eq(0).click();

        cy.contains(labelDeleteResource(pluralize(resourceType)));

        cy.contains(getLabelDeleteMany(pluralize(resourceType), 3));

        cy.makeSnapshot(
          `${resourceType}: displays the confirmation dialog when the massive delete button is clicked`
        );
      });

      it('sends a delete request for a single resource and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelDelete}_1`).click();

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@deleteOne');
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceDeleted(capitalize(resourceType)));

        cy.makeSnapshot(
          `${resourceType}: sends a delete request for a single resource and displays a success message`
        );
      });

      it('sends a delete request for multiple resources and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();
        cy.findAllByTestId(labelDelete).eq(0).click();

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@delete').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1, 2, 3]
          });
        });

        cy.waitForRequest('@getAll');

        cy.contains(labelResourceDeleted(pluralize(capitalize(resourceType))));

        cy.makeSnapshot(
          `${resourceType}: sends a delete request for multiple resources and displays a success message`
        );
      });
    });

    describe('duplicate', () => {
      it('displays the confirmation dialog when the inline duplicate button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelDuplicate}_1`).click();

        cy.contains(labelDuplicateResource(resourceType));

        cy.contains(
          getLabelDuplicateOne(
            resourceType,
            `${resourceType.replace(' ', '_')} 1`
          )
        );

        cy.contains(labelDuplications);

        cy.makeSnapshot(
          `${resourceType}: displays the confirmation dialog when the inline duplicate button is clicked`
        );
      });

      it('displays the confirmation dialog when the massive duplicate button is clicked', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();

        cy.findAllByTestId(labelDuplicate).eq(0).click();

        cy.contains(labelDuplicateResource(pluralize(resourceType)));

        cy.contains(getLabelDuplicateMany(pluralize(resourceType), 3));

        cy.contains(labelDuplications);

        cy.makeSnapshot(
          `${resourceType}: displays the confirmation dialog when the massive duplicate button is clicked`
        );
      });

      it('sends a duplicate request for a single resource and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelDuplicate}_1`).click();

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@duplicate').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1],
            nb_duplicates: 1
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceDuplicated(capitalize(resourceType)));

        cy.makeSnapshot(
          `${resourceType}: sends a duplicate request for a single resource and displays a success message`
        );
      });

      it('sends a duplicate request for multiple resources and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();
        cy.findAllByTestId(labelDuplicate).eq(0).click();

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@duplicate').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1, 2, 3],
            nb_duplicates: 1
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(
          labelResourceDuplicated(pluralize(capitalize(resourceType)))
        );

        cy.makeSnapshot(
          `${resourceType}: sends a duplicate request for multiple resources and displays a success message`
        );
      });

      it('sends a duplicate request for multiple resources with a custom number of duplications and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();
        cy.findAllByTestId(labelDuplicate).eq(0).click();

        cy.findAllByLabelText(labelDuplications)
          .eq(0)
          .should('have.value', '1');
        cy.findAllByLabelText(labelDuplications).eq(0).clear().type('4');

        cy.findByTestId('confirm').click();

        cy.waitForRequest('@duplicate').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1, 2, 3],
            nb_duplicates: 4
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(
          labelResourceDuplicated(pluralize(capitalize(resourceType)))
        );

        cy.makeSnapshot(
          `${resourceType}: sends a duplicate request for multiple resources with a custom number of duplications and displays a success message`
        );
      });
    });

    describe('Enable', () => {
      it('sends an Enable request for a single resource and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelEnableDisable}_2`).click();

        cy.waitForRequest('@enable').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [2]
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceEnabled(capitalize(resourceType)));

        cy.makeSnapshot(
          `${resourceType}: sends an Enable request for a single resource and displays a success message`
        );
      });

      it('sends an enable request for multiple resources and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();

        cy.findByLabelText(labelEnable).eq(0).click();

        cy.waitForRequest('@enable').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1, 2, 3]
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceEnabled(pluralize(capitalize(resourceType))));

        cy.makeSnapshot(
          `${resourceType}: sends an enable request for multiple resources and displays a success message`
        );
      });
    });

    describe('Disable', () => {
      it('sends a disable request for a single resource and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByTestId(`${labelEnableDisable}_1`).click();

        cy.waitForRequest('@disable').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1]
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceDisabled(capitalize(resourceType)));

        cy.makeSnapshot(
          `${resourceType}: sends a disable request for a single resource and displays a success message`
        );
      });

      it('sends a disbale request for multiple resources and displays a success message', () => {
        cy.waitForRequest('@getAll');

        cy.findByLabelText('Select row 1').click();
        cy.findByLabelText('Select row 2').click({ force: true });
        cy.findByLabelText('Select row 3').click();

        cy.findAllByTestId(labelMoreActions).eq(0).click();

        cy.findByLabelText(labelDisable).eq(0).click();

        cy.waitForRequest('@disable').then(({ request }) => {
          expect(request.body).to.deep.equal({
            ids: [1, 2, 3]
          });
        });
        cy.waitForRequest('@getAll');

        cy.contains(labelResourceDisabled(pluralize(capitalize(resourceType))));

        cy.makeSnapshot(
          `${resourceType}: sends a disbale request for multiple resources and displays a success message`
        );
      });
    });
  });
};
