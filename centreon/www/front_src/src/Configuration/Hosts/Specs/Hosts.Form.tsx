import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelDataProcessing,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelInvalidAddress,
  labelNameMustNotStartWithModule,
  labelNotification,
  labelRelations
} from '../translatedLabels';
import initialize from './initialize';

// `ConfigurationBase`'s specs pass `formVariant` explicitly, so this is the
// only place a revert to the modal would turn something red.
export default () => {
  describe('Form: ', () => {
    it('opens the form of a host in a side panel rather than a modal', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
        'be.visible'
      );
      cy.get('[data-testid="Modal"]').should('not.exist');

      // Named by the listing row, with no detail endpoint on this module.
      cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
        'have.text',
        'host 0'
      );
    });

    it('opens the creation form in the same panel', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
        'be.visible'
      );
      cy.get('[data-testid="Modal"]').should('not.exist');

      cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
        'have.text',
        'Add a host'
      );
    });

    it('declares the five sections the US defines', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      [
        labelHostConfiguration,
        labelNotification,
        labelRelations,
        labelDataProcessing,
        labelHostExtendedInfos
      ].forEach((section) => {
        cy.contains(section).should('be.visible');
      });
    });

    it('opens an existing host on the values the detail endpoint returns', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost').then(({ request }) => {
        // API Platform, not the legacy detail endpoint under `api/latest`.
        expect(request.url.pathname).to.contain('/api/configuration/hosts/0');
        expect(request.url.pathname).to.not.contain('/api/latest');
      });

      // Values of the detail response, none of which the listing row carries.
      cy.findAllByTestId('host-form-name')
        .eq(1)
        .should('have.value', 'host 0 as the detail endpoint spells it');
      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', '10.10.10.10');
      cy.findByTestId('host-form-poller').should('have.value', 'Poller EU');

      // The header keeps naming the row, which is what the listing showed.
      cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
        'have.text',
        'host 0'
      );
    });

    it('saves an edited host back to the host it was opened on', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-address').eq(1).clear().type('10.0.0.42');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      // The same PATCH route enable and disable use, on host 0 and no other.
      cy.waitForRequest('@patchHost').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/hosts/0');
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          name: 'host 0 as the detail endpoint spells it',
          poller_id: 2
        });
      });
    });

    it('names a host that has no icon of its own', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      // `skip_null_values` leaves host 1 without an `icon` key at all.
      cy.contains('host 1').click();

      cy.get(`[data-testid="${panelDataTestIds.header}"]`)
        .should('have.text', 'host 1')
        .parent()
        .find('img')
        .should('not.exist');
    });

    it('shows the host icon the listing carried into the panel', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.get(`[data-testid="${panelDataTestIds.header}"]`)
        .parent()
        .find('img')
        .should('have.attr', 'alt', 'server.png');
    });

    it('freezes the form for a user who may only look at hosts', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      // The host is still loaded and shown: frozen, not empty.
      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-name')
        .eq(1)
        .should('have.value', 'host 0 as the detail endpoint spells it')
        .and('be.disabled');
      cy.findAllByTestId('host-form-address').eq(1).should('be.disabled');

      // The autocomplete is a different rendering path from a text field, and
      // the one that can read as greyed while still offering its options.
      cy.findByTestId('host-form-poller').should('be.disabled');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'not.exist'
      );
    });

    it('drops the Notification section on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.contains(labelNotification).should('not.exist');

      [
        labelHostConfiguration,
        labelRelations,
        labelDataProcessing,
        labelHostExtendedInfos
      ].forEach((section) => {
        cy.contains(section).should('be.visible');
      });
    });

    it('refuses to save until the mandatory fields are filled', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'be.disabled'
      );

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'be.disabled'
      );
    });

    it('reports a name the API reserves for modules', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('_Module_BAM').blur();

      cy.contains(labelNameMustNotStartWithModule).should('be.visible');
    });

    it('reports an address that is neither an IP nor a resolvable name', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address').eq(1).type('not a host!').blur();

      cy.contains(labelInvalidAddress).should('be.visible');
    });

    it('creates a host from the three mandatory fields', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();

      // The same selector the listing filter reads, on API Platform rather
      // than the default base the shared autocomplete falls back to.
      cy.waitForRequest('@getPollers').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/pollers');
        expect(request.url.pathname).to.not.contain('/api/latest');
      });

      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      // API Platform, so the payload is snake_case and the poller is an id.
      // The glob matches both bases, so the base itself needs asserting.
      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/hosts');
        expect(request.url.pathname).to.not.contain('/api/latest');
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          name: 'srv-apache-02',
          poller_id: 2
        });
      });
    });
  });
};
