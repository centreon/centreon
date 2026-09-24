import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable,
  labelEnableDisable,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import {
  labelDeployServices,
  labelServiceDeploymentFailed,
  labelServicesDeployed
} from '../translatedLabels';
import initialize from './initialize';

// A deactivated row is not selectable, so indexing the enabled checkboxes is
// what actually maps to "the nth selectable host". Index 0 is select-all.
const selectSelectableRow = (index: number): void => {
  cy.get('input[type="checkbox"]:not([disabled])')
    .eq(index + 1)
    .click();
};

const selectFirstRow = (): void => selectSelectableRow(0);

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

      cy.waitForRequest('@deleteHost').then(({ request }) => {
        expect(request.url.pathname).to.contain('/configuration/hosts/0');
      });

      cy.makeSnapshot();
    });

    it('duplicates a host through the confirmation modal', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.findByTestId(`${labelDuplicate}_0`).click();

      cy.findByTestId('confirm').click();

      // The route names the host and takes no body.
      cy.waitForRequest('@duplicateHost').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/configuration/hosts/0/_duplicate'
        );
      });
    });

    it('deletes every selected host, one request each', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('input[type="checkbox"]').eq(0).click();

      cy.findByTestId(labelMoreActions).click();
      cy.get('[role="menu"]').contains(labelDelete).click();
      cy.findByTestId('confirm').click();

      // Sequentially, so waiting on the last one proves the whole fan-out ran.
      cy.waitForRequest('@deleteHost');
      cy.waitForRequest('@deleteHost2');
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

      cy.contains(labelServicesDeployed).should('be.visible');
    });

    it('reports a failed deployment with the agreed message, once', () => {
      initialize({ deployFails: true });

      cy.waitForRequest('@getAllHosts');

      selectFirstRow();

      cy.findByTestId(labelMoreActions).click();
      cy.contains(labelDeployServices).click();

      cy.waitForRequest('@deployServices');

      cy.contains(labelServiceDeploymentFailed).should('be.visible');

      // The API's own message is suppressed so the two do not stack.
      cy.contains('Host not found').should('not.exist');
      cy.contains(labelServicesDeployed).should('not.exist');
    });

    it('offers every massive action the ticket lists, and no massive change', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      selectFirstRow();

      cy.findByTestId(labelMoreActions).click();

      // A positive assertion on the whole menu: it fails if an entry goes
      // missing and if massive change appears before its own ticket lands.
      cy.get('[role="menu"]')
        .findAllByRole('menuitem')
        .should('have.length', 5)
        .then((entries) => {
          expect([...entries].map((entry) => entry.textContent)).to.deep.equal([
            labelDuplicate,
            labelEnable,
            labelDisable,
            labelDelete,
            labelDeployServices
          ]);
        });

      cy.makeSnapshot();
    });

    it('disables every selected host, not just the first', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      // Select-all takes every selectable row — hosts 0 and 2; host 1 is
      // deactivated and cannot be selected.
      cy.get('input[type="checkbox"]').eq(0).click();

      cy.findByTestId(labelMoreActions).click();
      cy.get('[role="menu"]').contains(labelDisable).click();

      // Waiting on the second host is the whole point: patching only `ids[0]`
      // while reporting the entire selection as done is the bug this pins, and
      // it is invisible if you assert on the first host.
      cy.waitForRequest('@patchHost2').then(({ request }) => {
        expect(request.body).to.deep.equal({ is_activated: false });
      });
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
