import {
  labelDelete,
  labelDuplicate,
  labelEnableDisable,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import { labelDeployServices } from '../translatedLabels';
import initialize from './initialize';

const selectFirstRow = (): void => {
  cy.get('input[type="checkbox"]').eq(1).click();
};

export default () => {
  describe('Actions: ', () => {
    it('links the services icon to the legacy services listing for that host', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.window().then((window) => {
        cy.stub(window, 'open').as('open');
      });

      cy.findByTestId('go-to-services_0').click();

      cy.get('@open').should(
        'have.been.calledWith',
        '/main.php?p=60201&search=host%200',
        '_self'
      );
    });

    it('deletes a host through the confirmation modal', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.findByTestId(`${labelDelete}_0`).click();

      cy.findByTestId('confirm').click();

      cy.waitForRequest('@deleteHost');

      cy.makeSnapshot();
    });

    it('duplicates a host through the confirmation modal', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.findByTestId(`${labelDuplicate}_0`).click();

      cy.findByTestId('confirm').click();

      cy.waitForRequest('@duplicateHosts');

      cy.makeSnapshot();
    });

    it('disables an activated host from the row toggle', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.findByTestId(`${labelEnableDisable}_0`).click();

      cy.waitForRequest('@patchHost').then(({ request }) => {
        expect(request.body).to.deep.equal({ is_activated: false });
      });
    });

    it('offers deploy services in the More actions menu', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      selectFirstRow();

      cy.findByTestId(labelMoreActions).click();

      cy.contains(labelDeployServices).should('be.visible').click();

      cy.waitForRequest('@deployServices');

      cy.makeSnapshot();
    });

    it('does not offer massive change, which is not implemented yet', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      selectFirstRow();

      cy.findByTestId(labelMoreActions).click();

      cy.contains('Massive change').should('not.exist');

      cy.makeSnapshot();
    });

    describe('Read-only ACL user: ', () => {
      beforeEach(() => {
        initialize({ hasWriteAccess: false });

        cy.waitForRequest('@getAllHosts');
      });

      it('keeps the services icon and the toggle, the latter disabled', () => {
        cy.findByTestId('go-to-services_0').should('be.visible');

        cy.findByTestId(`${labelEnableDisable}_0`)
          .find('input')
          .should('be.disabled');

        cy.makeSnapshot();
      });

      it('hides the add button, more actions and the row write actions', () => {
        cy.findByTestId(labelMoreActions).should('not.exist');
        cy.findByTestId('add-resource').should('not.exist');
        cy.findByTestId(`${labelDelete}_0`).should('not.exist');
        cy.findByTestId(`${labelDuplicate}_0`).should('not.exist');
      });
    });
  });
};
