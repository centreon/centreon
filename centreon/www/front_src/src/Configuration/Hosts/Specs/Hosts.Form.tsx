import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelChildHosts,
  labelDataProcessing,
  labelDefault,
  labelDown,
  labelDowntimeScheduled,
  labelFlapping,
  labelHostCategories,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelHostGroups,
  labelHostNotFound,
  labelInvalidAddress,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelNameMustNotStartWithModule,
  labelNo,
  labelNone,
  labelNotification,
  labelParentHosts,
  labelRecovery,
  labelRelations,
  labelResolve,
  labelUnreachable,
  labelYes
} from '../translatedLabels';
import initialize, { pollersForbiddenMessage } from './initialize';
import {
  refusedAddressResponse,
  resolvedAddressResponse,
  untouchedNotificationsPayload
} from './utils';

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
      cy.findAllByTestId('host-form-alias')
        .eq(1)
        .should(
          'have.value',
          'alias of host 0 as the detail endpoint spells it'
        );
      // Once the default is known too, so it cannot be what this checks.
      cy.waitForRequest('@getFormPollers');
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
          alias: 'alias of host 0 as the detail endpoint spells it',
          category_ids: [4],
          child_host_ids: [2],
          host_group_ids: [1],
          name: 'host 0 as the detail endpoint spells it',
          notifications: {
            contact_groups: [3],
            contacts: [1],
            enabled: 'false',
            first_delay: 1,
            interval: 3,
            options: ['down', 'recovery'],
            recovery_delay: null,
            timeperiod_id: 1
          },
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

    it('makes the form read-only for a user who may only look at hosts', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      // The host is still loaded and shown: read-only, not empty.
      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-name')
        .eq(1)
        .should('have.value', 'host 0 as the detail endpoint spells it')
        .and('be.disabled');
      cy.findAllByTestId('host-form-address').eq(1).should('be.disabled');

      // The autocomplete is a different rendering path from a text field, and
      // the one that can read as greyed while still offering its options.
      cy.findByTestId('host-form-poller').should('be.disabled');
      [
        'host-form-groups',
        'host-form-categories',
        'host-form-parent-hosts',
        'host-form-child-hosts',
        'host-form-notifications-contacts',
        'host-form-notifications-contact-groups',
        'host-form-notifications-timeperiod'
      ].forEach((testId) => {
        cy.findByTestId(testId).should('be.disabled');
      });
      cy.findAllByTestId('host-form-notifications-interval')
        .eq(1)
        .should('be.disabled');
      cy.findByTestId('host-form-notifications-enabled')
        .findByRole('button', { name: labelYes })
        .should('be.disabled');
      cy.findByTestId(`host-form-notifications-options-${labelDown}`).should(
        'have.attr',
        'aria-disabled',
        'true'
      );

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

    it('replaces the address with the IP its name resolves to', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .type(` ${resolvedAddressResponse.hostname} `);

      cy.findByTestId('host-form-address-resolve')
        .should('have.text', labelResolve)
        .click();

      // The glob matches both bases, so the base itself needs asserting.
      cy.waitForRequest('@resolveAddress').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/_resolve'
        );
        expect(request.url.pathname).to.not.contain('/api/latest');
        expect(request.url.searchParams.get('hostname')).to.equal(
          resolvedAddressResponse.hostname
        );
      });

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', resolvedAddressResponse.ip);
      cy.contains(
        `${resolvedAddressResponse.hostname} → ${resolvedAddressResponse.ip}`
      ).should('be.visible');
    });

    it('keeps an address that does not resolve and says so', () => {
      initialize({ addressResolution: 'unresolved' });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .type(resolvedAddressResponse.hostname);

      cy.findByTestId('host-form-address-resolve').click();

      cy.waitForRequest('@resolveAddress');

      cy.contains(labelHostNotFound).should('be.visible');
      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', resolvedAddressResponse.hostname);
    });

    it('shows why the API refuses to resolve an address', () => {
      initialize({ addressResolution: 'refused' });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address').eq(1).type('srv_01');

      cy.findByTestId('host-form-address-resolve').click();

      cy.waitForRequest('@resolveAddress');

      cy.contains(refusedAddressResponse.message.trim()).should('be.visible');
      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', 'srv_01');
    });

    it('offers to resolve the address only once there is one', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByTestId('host-form-address-resolve').should('be.disabled');

      // Blanks are trimmed before they are sent, so they count as nothing.
      cy.findAllByTestId('host-form-address').eq(1).type('   ');
      cy.findByTestId('host-form-address-resolve').should('be.disabled');

      cy.findAllByTestId('host-form-address').eq(1).type('srv-apache-02');
      cy.findByTestId('host-form-address-resolve').should('be.enabled');
    });

    it('offers no resolution for an address that already is an IPv4', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');
      cy.findByTestId('host-form-address-resolve').should('be.disabled');

      cy.findAllByTestId('host-form-address').eq(1).type('.example.com');
      cy.findByTestId('host-form-address-resolve').should('be.enabled');
    });

    it('does not let a user who may only look at hosts resolve an address', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 1').click();

      cy.waitForRequest('@getHost1');

      // A name, so only the missing write access disables it.
      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', 'host-1.example.com');
      cy.findByTestId('host-form-address-resolve').should('be.disabled');
    });

    it('offers no resolve button on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address').eq(1).type('srv-apache-02');

      cy.findByTestId('host-form-address-resolve').should('not.exist');
    });

    it('opens the creation form on the default monitoring server', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      // The form's own selector, the one the poller field reads.
      cy.waitForRequest('@getFormPollers').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/pollers'
        );
        expect(request.url.pathname).to.not.contain('/api/latest');
      });

      cy.get('[data-testid="add-resource"]').click();

      cy.findByTestId('host-form-poller').should('have.value', 'Poller US');

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.poller_id).to.equal(3);
      });
    });

    it('keeps what was typed when the default monitoring server arrives late', () => {
      initialize({ pollersDelay: 1500 });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');

      // The request went out with the page, so only time tells the response
      // has landed: nothing visible changes when it does.
      cy.wait(2000);

      // A late default would reinitialise the form; this one goes without.
      cy.findAllByTestId('host-form-name')
        .eq(1)
        .should('have.value', 'srv-apache-02');
      cy.findByTestId('host-form-poller').should('have.value', '');
    });

    it('opens the creation form with no monitoring server when none is the default', () => {
      initialize({ hasDefaultPoller: false });

      cy.waitForRequest('@getAllHosts');
      cy.waitForRequest('@getFormPollers');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').should('exist');
      cy.findByTestId('host-form-poller').should('have.value', '');
    });

    it('does not look the default monitoring server up for a user who may only look at hosts', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      // The selector would answer 403, and the listing would say so.
      cy.findAllByTestId('host-form-name').should('exist');
      cy.contains(pollersForbiddenMessage).should('not.exist');
    });

    it('resolves an address once while a lookup is running', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .type(resolvedAddressResponse.hostname);

      cy.findByTestId('host-form-address-resolve').click();
      cy.findByTestId('host-form-address-resolve')
        .should('be.disabled')
        .click({ force: true });

      cy.waitForRequest('@resolveAddress');

      cy.getRequestCalls('@resolveAddress').then((calls) => {
        expect(calls).to.have.length(1);
      });
    });

    it('keeps an address edited while its previous value was resolving', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .type(resolvedAddressResponse.hostname);

      cy.findByTestId('host-form-address-resolve').click();

      cy.findAllByTestId('host-form-address').eq(1).clear().type('srv-b');

      cy.waitForRequest('@resolveAddress');

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', 'srv-b');
    });

    it('creates a host with its alias, trimmed', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');
      cy.waitForRequest('@getFormPollers');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-alias').eq(1).type('  Apache front  ');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.alias).to.equal('Apache front');
      });
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
          alias: null,
          category_ids: [],
          child_host_ids: [],
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
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
          alias: null,
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
          alias: null,
          category_ids: [],
          child_host_ids: [],
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
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

      // Found by label, so two swapped labels turn this red.
      [
        [labelHostGroups, 'Linux servers'],
        [labelHostCategories, 'Virtual'],
        [labelParentHosts, 'host 1'],
        [labelChildHosts, 'host 2']
      ].forEach(([label, chip]) => {
        cy.findByLabelText(label)
          .closest('.MuiAutocomplete-root')
          .find('.MuiChip-root')
          .should('have.length', 1)
          .and('have.text', chip);
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
      cy.get('.MuiAutocomplete-popper').contains('host 1').click();
      cy.focused().type('{esc}');

      cy.findByTestId('host-form-child-hosts').click();
      cy.get('.MuiAutocomplete-popper').contains('host 2').click();
      cy.focused().type('{esc}');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body).to.deep.equals({
          address: '10.0.0.42',
          alias: null,
          category_ids: [3],
          child_host_ids: [2],
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
          parent_host_ids: [1],
          poller_id: 2
        });
      });
    });

    it('opens an existing host on its notification settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByRole('button', { name: labelNo }).should(
        'have.attr',
        'aria-pressed',
        'true'
      );

      [
        [labelLinkedContacts, 'admin'],
        [labelLinkedContactGroups, 'Supervisors']
      ].forEach(([label, chip]) => {
        cy.findByLabelText(label)
          .closest('.MuiAutocomplete-root')
          .find('.MuiChip-root')
          .should('have.length', 1)
          .and('have.text', chip);
      });

      cy.findAllByTestId('host-form-notifications-interval')
        .eq(1)
        .should('have.value', '3');
      cy.findAllByTestId('host-form-notifications-firstDelay')
        .eq(1)
        .should('have.value', '1');
      // Left out of the response, so still empty.
      cy.findAllByTestId('host-form-notifications-recoveryDelay')
        .eq(1)
        .should('have.value', '');
      cy.findByTestId('host-form-notifications-timeperiod').should(
        'have.value',
        '24x7'
      );

      cy.findByTestId(`host-form-notifications-options-${labelDown}`).should(
        'have.attr',
        'aria-pressed',
        'true'
      );
      cy.findByTestId(
        `host-form-notifications-options-${labelRecovery}`
      ).should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId(
        `host-form-notifications-options-${labelUnreachable}`
      ).should('have.attr', 'aria-pressed', 'false');
    });

    it('opens a host with no notification settings on their defaults', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      // Host 1's detail response has no `notifications` block at all.
      cy.contains('host 1').click();

      cy.waitForRequest('@getHost1');

      cy.findByRole('button', { name: labelDefault }).should(
        'have.attr',
        'aria-pressed',
        'true'
      );

      cy.findAllByTestId('host-form-address').eq(1).clear().type('10.0.0.42');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@patchHost1').then(({ request }) => {
        expect(request.body.notifications).to.deep.equals(
          untouchedNotificationsPayload
        );
      });
    });

    it('creates a host with its notification settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      // Default is preselected, and is not No.
      cy.findByRole('button', { name: labelDefault }).should(
        'have.attr',
        'aria-pressed',
        'true'
      );
      cy.findByRole('button', { name: labelYes }).click();

      cy.findByTestId('host-form-notifications-contacts').click();
      cy.waitForRequest('@getFormContacts').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/contacts'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('admin').click();
      // A multi-select stays open after a pick.
      cy.focused().type('{esc}');

      cy.findByTestId('host-form-notifications-contact-groups').click();
      cy.waitForRequest('@getFormContactGroups').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/contact_groups'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('Supervisors').click();
      cy.focused().type('{esc}');

      cy.findAllByTestId('host-form-notifications-interval').eq(1).type('5');

      cy.findByTestId('host-form-notifications-timeperiod').click();
      cy.waitForRequest('@getFormTimePeriods').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/timeperiods'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('workhours').click();

      cy.findByTestId(`host-form-notifications-options-${labelDown}`).click();
      cy.findByTestId(
        `host-form-notifications-options-${labelFlapping}`
      ).click();
      cy.findByTestId(
        `host-form-notifications-options-${labelUnreachable}`
      ).click();
      cy.findByTestId(
        `host-form-notifications-options-${labelDowntimeScheduled}`
      ).click();

      cy.findAllByTestId('host-form-notifications-firstDelay').eq(1).type('0');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      // `enabled` as the string the API takes, not a boolean.
      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.notifications).to.deep.equals({
          contact_groups: [3],
          contacts: [1],
          enabled: 'true',
          first_delay: 0,
          interval: 5,
          options: ['down', 'flapping', 'unreachable', 'downtime_scheduled'],
          recovery_delay: null,
          timeperiod_id: 2
        });
      });
    });

    it('sends None alone once it is chosen', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.findByTestId(`host-form-notifications-options-${labelDown}`).click();
      cy.findByLabelText(labelNone).click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.notifications.options).to.deep.equals(['none']);
      });
    });

    it('hides the additive inheritance toggles the platform does not use', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').should('exist');
      cy.findByTestId(
        'host-form-notifications-contact-additive-inheritance'
      ).should('not.exist');
      cy.findByTestId(
        'host-form-notifications-contact-group-additive-inheritance'
      ).should('not.exist');
    });

    it('sends the additive inheritance toggles where the platform uses them', () => {
      initialize({ isAdditiveInheritanceEnabled: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.findByTestId(
        'host-form-notifications-contact-additive-inheritance'
      ).click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.notifications).to.deep.equals({
          ...untouchedNotificationsPayload,
          contact_additive_inheritance: true,
          contact_group_additive_inheritance: false
        });
      });
    });

    it('opens and saves back the additive inheritance a host carries', () => {
      initialize({ isAdditiveInheritanceEnabled: true });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByTestId('host-form-notifications-contact-additive-inheritance')
        .find('input')
        .should('be.checked');
      cy.findByTestId(
        'host-form-notifications-contact-group-additive-inheritance'
      )
        .find('input')
        .should('not.be.checked');

      cy.findAllByTestId('host-form-address').eq(1).clear().type('10.0.0.42');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@patchHost').then(({ request }) => {
        expect(request.body.notifications).to.include({
          contact_additive_inheritance: true,
          contact_group_additive_inheritance: false
        });
      });
    });
  });
};
