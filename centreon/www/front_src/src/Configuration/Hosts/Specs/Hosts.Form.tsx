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
          category_ids: [4],
          child_host_ids: [2],
          host_group_ids: [1],
          name: 'host 0 as the detail endpoint spells it',
          parent_host_ids: [1],
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

    it('leaves host groups optional on an onPrem platform', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      // Nothing picked in Relations, and the form is still saveable.
      cy.findByTestId('host-form-groups').should('not.have.attr', 'required');
      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'be.enabled'
      );

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      // Trimmed on the way out: the schema validates the trimmed value, so an
      // untrimmed one would pass `max` here and fail it server side.
      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          category_ids: [],
          child_host_ids: [],
          host_group_ids: [],
          name: 'srv-apache-02',
          parent_host_ids: [],
          poller_id: 2
        });
      });
    });

    it('requires a host group before saving on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      // The same three fields that suffice onPrem leave cloud incomplete.
      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).should(
        'be.disabled'
      );

      // The field says so too: without this the rule and the input could be
      // pinned to two flags that merely happen to agree.
      cy.findByTestId('host-form-groups').should('have.attr', 'required');

      cy.findByTestId('host-form-groups').click();

      cy.waitForRequest('@getFormHostGroups').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/host_groups'
        );
        expect(request.url.pathname).to.not.contain('/api/latest');
      });

      cy.get('.MuiAutocomplete-popper').contains('Linux servers').click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          category_ids: [],
          child_host_ids: [],
          host_group_ids: [1],
          name: 'srv-apache-02',
          parent_host_ids: [],
          poller_id: 2
        });
      });
    });

    it('creates a host from the three mandatory fields', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();

      // The form's own selector, not the generic one the listing filter reads:
      // it is granted by host write access and offers only active pollers.
      cy.waitForRequest('@getFormPollers').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/pollers'
        );
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
          category_ids: [],
          child_host_ids: [],
          host_group_ids: [],
          name: 'srv-apache-02',
          parent_host_ids: [],
          poller_id: 2
        });
      });
    });

    it('opens an existing host on the relations the detail endpoint returns', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      // The listing behind the panel shows the same host names.
      cy.get(`[data-testid="${panelDataTestIds.content}"]`).within(() => {
        cy.contains('.MuiChip-root', 'Linux servers').should('be.visible');
        cy.contains('.MuiChip-root', 'Virtual').should('be.visible');
        cy.contains('.MuiChip-root', 'host 1').should('be.visible');
        cy.contains('.MuiChip-root', 'host 2').should('be.visible');
      });
    });

    it('creates a host with its categories, parent and child hosts', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.findByTestId('host-form-categories').click();

      cy.waitForRequest('@getFormHostCategories').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/host_categories'
        );
        expect(request.url.pathname).to.not.contain('/api/latest');
      });

      cy.get('.MuiAutocomplete-popper').contains('Physical').click();
      // A multi-select stays open after a pick.
      cy.focused().type('{esc}');

      cy.findByTestId('host-form-parent-hosts').click();

      cy.waitForRequest('@getAllHosts').then(({ request }) => {
        expect(request.url.pathname).to.match(/\/api\/configuration\/hosts$/);
      });

      cy.get('.MuiAutocomplete-popper').contains('host 1').click();
      cy.focused().type('{esc}');

      cy.findByTestId('host-form-child-hosts').click();
      cy.get('.MuiAutocomplete-popper').contains('host 2').click();
      cy.focused().type('{esc}');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          category_ids: [3],
          child_host_ids: [2],
          host_group_ids: [],
          name: 'srv-apache-02',
          parent_host_ids: [1],
          poller_id: 2
        });
      });
    });

    it('freezes the relations for a user who may only look at hosts', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      [
        'host-form-groups',
        'host-form-categories',
        'host-form-parent-hosts',
        'host-form-child-hosts'
      ].forEach((testId) => {
        cy.findByTestId(testId).should('be.disabled');
      });
    });
  });
};
