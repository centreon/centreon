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
  labelInvalidGeographicCoordinates,
  labelLinkedContactGroups,
  labelLinkedContacts,
  labelMustBeAPercentage,
  labelMustBeIntegerOfAtLeastOne,
  labelNameMustNotStartWithModule,
  labelNo,
  labelNone,
  labelNotification,
  labelParentHosts,
  labelRecovery,
  labelRelations,
  labelResolve,
  labelSnmpVersion,
  labelTimezone,
  labelUnreachable,
  labelYes
} from '../translatedLabels';
import initialize, { pollersForbiddenMessage } from './initialize';
import {
  refusedAddressResponse,
  resolvedAddressResponse,
  untouchedCheckOptionsPayload,
  untouchedDataProcessingPayload,
  untouchedExtendedInformationsPayload,
  untouchedNotificationsPayload,
  untouchedSchedulingOptionsPayload
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
          // The arguments go back as the list they came as.
          check_options: { args: ['3', '80%'], command_id: 9 },
          child_host_ids: [2],
          // The arguments go back as the list they came as.
          data_processing: {
            acknowledgment_timeout: 15,
            check_freshness: 'true',
            event_handler_args: ['80', 'graceful'],
            event_handler_command_id: 7,
            event_handler_enabled: 'false',
            flap_detection_enabled: 'true',
            freshness_threshold: 120,
            high_flap_threshold: 50,
            low_flap_threshold: null
          },
          extended_informations: {
            action_url: 'https://example.com/actions/host-0',
            alt_icon: null,
            comment: 'Racked in room B',
            geo_coordinates: '48.8566,2.3522',
            icon_id: 12,
            note: 'Front web server',
            note_url: null
          },
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
          poller_id: 2,
          scheduling_options: {
            active_check_enabled: 'false',
            check_timeperiod_id: 2,
            max_check_attempts: 3,
            normal_check_interval: 5,
            passive_check_enabled: 'true',
            retry_check_interval: null
          },
          severity_id: 2,
          // No `snmp_community`: left empty, it is left unchanged.
          snmp_version: '2c',
          timezone_id: 2
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

    it('offers no resolution for an address that already is an IP', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');
      cy.findByTestId('host-form-address-resolve').should('be.disabled');

      cy.findAllByTestId('host-form-address').eq(1).clear().type('fe80::1');
      cy.findByTestId('host-form-address-resolve').should('be.disabled');

      cy.findAllByTestId('host-form-address').eq(1).clear().type('10.0.0.42');

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
      // Unresolved, so the address stays a name: only the running lookup
      // can disable the button.
      initialize({ addressResolution: 'unresolved' });

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
      initialize({ resolveDelay: 1500 });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .type(resolvedAddressResponse.hostname);

      cy.findByTestId('host-form-address-resolve').click();

      cy.findAllByTestId('host-form-address').eq(1).clear().type('srv-b');

      cy.waitForRequest('@resolveAddress');
      // The request went out at the click; nothing visible marks its answer.
      cy.wait(2000);

      cy.findAllByTestId('host-form-address')
        .eq(1)
        .should('have.value', 'srv-b'); // Nor is a result it did not apply reported.
      cy.contains(resolvedAddressResponse.ip).should('not.exist');
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

    it('sends no alias for a host whose alias was cleared', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-alias')
        .eq(1)
        .should(
          'have.value',
          'alias of host 0 as the detail endpoint spells it'
        )
        .clear();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@patchHost').then(({ request }) => {
        expect(request.body.alias).to.equal(null);
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
          check_options: untouchedCheckOptionsPayload,
          child_host_ids: [],
          data_processing: untouchedDataProcessingPayload,
          extended_informations: untouchedExtendedInformationsPayload,
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
          parent_host_ids: [],
          poller_id: 2,
          scheduling_options: untouchedSchedulingOptionsPayload,
          severity_id: null,
          snmp_version: null,
          timezone_id: null
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
          check_options: untouchedCheckOptionsPayload,
          child_host_ids: [],
          // The onPrem-only fields are refused on cloud.
          data_processing: {
            check_freshness: 'use_default',
            event_handler_command_id: null,
            event_handler_enabled: 'use_default',
            freshness_threshold: null
          },
          // Alt icon and comments are refused on cloud.
          extended_informations: {
            action_url: null,
            geo_coordinates: null,
            icon_id: null,
            note: null,
            note_url: null
          },
          host_group_ids: [1],
          name: 'srv-apache-02',
          parent_host_ids: [],
          poller_id: 2,
          // The check toggles are refused on cloud.
          scheduling_options: {
            check_timeperiod_id: null,
            max_check_attempts: null,
            normal_check_interval: null,
            retry_check_interval: null
          },
          severity_id: null,
          snmp_version: null,
          timezone_id: null
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
          check_options: untouchedCheckOptionsPayload,
          child_host_ids: [],
          data_processing: untouchedDataProcessingPayload,
          extended_informations: untouchedExtendedInformationsPayload,
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
          parent_host_ids: [],
          poller_id: 2,
          scheduling_options: untouchedSchedulingOptionsPayload,
          severity_id: null,
          snmp_version: null,
          timezone_id: null
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
          check_options: untouchedCheckOptionsPayload,
          child_host_ids: [2],
          data_processing: untouchedDataProcessingPayload,
          extended_informations: untouchedExtendedInformationsPayload,
          host_group_ids: [],
          name: 'srv-apache-02',
          notifications: untouchedNotificationsPayload,
          parent_host_ids: [1],
          poller_id: 2,
          scheduling_options: untouchedSchedulingOptionsPayload,
          severity_id: null,
          snmp_version: null,
          timezone_id: null
        });
      });
    });

    it('opens an existing host on its notification settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByTestId('host-form-notifications-enabled')
        .findByRole('button', { name: labelNo })
        .should('have.attr', 'aria-pressed', 'true');

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

      cy.findByTestId('host-form-notifications-enabled')
        .findByRole('button', { name: labelDefault })
        .should('have.attr', 'aria-pressed', 'true');

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
      cy.findByTestId('host-form-notifications-enabled')
        .findByRole('button', { name: labelDefault })
        .should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId('host-form-notifications-enabled')
        .findByRole('button', { name: labelYes })
        .click();

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

    it('opens an existing host on its SNMP, timezone and scheduling settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      // Write-only: nothing to open on.
      cy.findAllByTestId('host-form-snmp-community')
        .eq(1)
        .should('have.value', '')
        .and('have.attr', 'type', 'password');
      cy.findByLabelText(labelSnmpVersion).should('have.value', '2c');
      cy.findByTestId('host-form-timezone').should(
        'have.value',
        'Europe/Paris'
      );

      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .should('have.value', '3');
      cy.findAllByTestId('host-form-scheduling-options-normalCheckInterval')
        .eq(1)
        .should('have.value', '5');
      // Left out of the response, so still empty.
      cy.findAllByTestId('host-form-scheduling-options-retryCheckInterval')
        .eq(1)
        .should('have.value', '');

      cy.findByTestId(
        'host-form-scheduling-options-activeCheckEnabled-false'
      ).should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId(
        'host-form-scheduling-options-passiveCheckEnabled-true'
      ).should('have.attr', 'aria-pressed', 'true');
    });

    it('creates a host with its SNMP, timezone and scheduling settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.findAllByTestId('host-form-snmp-community').eq(1).type('public');

      cy.findByLabelText(labelSnmpVersion).click();
      cy.get('.MuiAutocomplete-popper').contains('2c').click();

      cy.findByTestId('host-form-timezone').click();
      // No host-scoped timezone selector exists; this one is on API Platform.
      cy.waitForRequest('@getFormTimezones').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/timezones');
        expect(request.url.pathname).to.not.contain('/api/latest');
      });
      cy.get('.MuiAutocomplete-popper').contains('Europe/Paris').click();

      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .type('3');
      cy.findAllByTestId('host-form-scheduling-options-retryCheckInterval')
        .eq(1)
        .type('1');

      // Default is preselected, and is not No.
      cy.findByTestId(
        'host-form-scheduling-options-activeCheckEnabled-use_default'
      ).should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId(
        'host-form-scheduling-options-activeCheckEnabled-true'
      ).click();
      cy.findByTestId(
        'host-form-scheduling-options-passiveCheckEnabled-false'
      ).click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body).to.include({
          snmp_community: 'public',
          snmp_version: '2c',
          timezone_id: 2
        });
        expect(request.body.scheduling_options).to.deep.equals({
          active_check_enabled: 'true',
          check_timeperiod_id: null,
          max_check_attempts: 3,
          normal_check_interval: null,
          passive_check_enabled: 'false',
          retry_check_interval: 1
        });
      });
    });

    it('refuses a check interval below 1', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-scheduling-options-normalCheckInterval')
        .eq(1)
        .type('0')
        .blur();

      cy.contains(labelMustBeIntegerOfAtLeastOne).should('be.visible');
    });

    it('offers no check toggles on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .should('be.visible');
      cy.findByTestId('host-form-scheduling-options-activeCheckEnabled').should(
        'not.exist'
      );
      cy.findByTestId(
        'host-form-scheduling-options-passiveCheckEnabled'
      ).should('not.exist');
    });

    it('lets a user who may only look at hosts change none of them', () => {
      initialize({ hasWriteAccess: false });

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-snmp-community')
        .eq(1)
        .should('be.disabled');
      cy.findByLabelText(labelSnmpVersion).should('be.disabled');
      cy.findByLabelText(labelTimezone).should('be.disabled');
      cy.findAllByTestId('host-form-scheduling-options-maxCheckAttempts')
        .eq(1)
        .should('be.disabled');
      cy.findByTestId(
        'host-form-scheduling-options-activeCheckEnabled-true'
      ).should('be.disabled');
    });

    it('opens an existing host on its check command and check period', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByTestId('host-form-check-options-command').should(
        'have.value',
        'check-host-alive'
      );
      cy.findAllByTestId('host-form-check-options-args')
        .eq(1)
        .should('have.value', '!3!80%')
        .and('be.enabled');
      cy.findByTestId('host-form-scheduling-options-checkPeriod').should(
        'have.value',
        'workhours'
      );
    });

    it('creates a host with its check command, arguments and check period', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      // Arguments are refused without a command.
      cy.findAllByTestId('host-form-check-options-args')
        .eq(1)
        .should('be.disabled');

      cy.findByTestId('host-form-check-options-command').click();
      // Only active check commands, as legacy offers.
      cy.waitForRequest('@getFormCommands').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/commands');
        expect(request.url.searchParams.getAll('type[]')).to.deep.equals([
          'Check'
        ]);
        expect(request.url.searchParams.get('is_activated')).to.equal('true');
      });
      cy.get('.MuiAutocomplete-popper').contains('check-host-alive').click();

      cy.findAllByTestId('host-form-check-options-args')
        .eq(1)
        .should('be.enabled')
        .type('3!80%');

      cy.findByTestId('host-form-scheduling-options-checkPeriod').click();
      cy.waitForRequest('@getFormTimePeriods').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/timeperiods'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('workhours').click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.check_options).to.deep.equals({
          args: ['3', '80%'],
          command_id: 9
        });
        expect(request.body.scheduling_options).to.deep.equals({
          ...untouchedSchedulingOptionsPayload,
          check_timeperiod_id: 2
        });
      });
    });

    it('sends no arguments once the check command is removed', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByTestId('host-form-check-options-command')
        .parents('.MuiAutocomplete-root')
        .find('.MuiAutocomplete-clearIndicator')
        .click({ force: true });

      cy.findAllByTestId('host-form-check-options-args')
        .eq(1)
        .should('be.disabled');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@patchHost').then(({ request }) => {
        expect(request.body.check_options).to.deep.equals(
          untouchedCheckOptionsPayload
        );
      });
    });

    it('offers the check command and check period on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByTestId('host-form-check-options-command').should('be.visible');
      cy.findAllByTestId('host-form-check-options-args')
        .eq(1)
        .should('be.visible');
      cy.findByTestId('host-form-scheduling-options-checkPeriod').should(
        'be.visible'
      );
    });

    it('opens an existing host on its data processing settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findByTestId('host-form-data-processing-checkFreshness-true').should(
        'have.attr',
        'aria-pressed',
        'true'
      );
      cy.findAllByTestId('host-form-data-processing-freshnessThreshold')
        .eq(1)
        .should('have.value', '120');
      cy.findAllByTestId('host-form-data-processing-acknowledgmentTimeout')
        .eq(1)
        .should('have.value', '15');
      cy.findByTestId(
        'host-form-data-processing-flapDetectionEnabled-true'
      ).should('have.attr', 'aria-pressed', 'true');
      // Left out of the response, so still empty.
      cy.findAllByTestId('host-form-data-processing-lowFlapThreshold')
        .eq(1)
        .should('have.value', '');
      cy.findAllByTestId('host-form-data-processing-highFlapThreshold')
        .eq(1)
        .should('have.value', '50');
      cy.findByTestId(
        'host-form-data-processing-eventHandlerEnabled-false'
      ).should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId('host-form-data-processing-eventHandler').should(
        'have.value',
        'restart-httpd'
      );
      // The list the API returns, written the way legacy writes it.
      cy.findAllByTestId('host-form-data-processing-eventHandlerArgs')
        .eq(1)
        .should('have.value', '!80!graceful');
    });

    it('creates a host with its data processing settings', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      // Default is preselected, and is not No.
      cy.findByTestId(
        'host-form-data-processing-checkFreshness-use_default'
      ).should('have.attr', 'aria-pressed', 'true');
      cy.findByTestId('host-form-data-processing-checkFreshness-true').click();
      cy.findAllByTestId('host-form-data-processing-freshnessThreshold')
        .eq(1)
        .type('300');
      cy.findAllByTestId('host-form-data-processing-acknowledgmentTimeout')
        .eq(1)
        .type('10');
      cy.findByTestId(
        'host-form-data-processing-flapDetectionEnabled-false'
      ).click();
      cy.findAllByTestId('host-form-data-processing-lowFlapThreshold')
        .eq(1)
        .type('20');
      cy.findAllByTestId('host-form-data-processing-highFlapThreshold')
        .eq(1)
        .type('40');
      cy.findByTestId(
        'host-form-data-processing-eventHandlerEnabled-true'
      ).click();

      cy.findByTestId('host-form-data-processing-eventHandler').click();
      // No host-scoped command selector exists; this one is on API Platform
      // and, as legacy, offers active commands only.
      cy.waitForRequest('@getFormCommands').then(({ request }) => {
        expect(request.url.pathname).to.contain('/api/configuration/commands');
        expect(request.url.pathname).to.not.contain('/api/latest');
        expect(request.url.searchParams.get('is_activated')).to.equal('true');
      });
      cy.get('.MuiAutocomplete-popper').contains('restart-httpd').click();

      // The leading `!` is optional.
      cy.findAllByTestId('host-form-data-processing-eventHandlerArgs')
        .eq(1)
        .type('80!!graceful');

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.data_processing).to.deep.equals({
          acknowledgment_timeout: 10,
          check_freshness: 'true',
          event_handler_args: ['80', '', 'graceful'],
          event_handler_command_id: 7,
          event_handler_enabled: 'true',
          flap_detection_enabled: 'false',
          freshness_threshold: 300,
          high_flap_threshold: 40,
          low_flap_threshold: 20
        });
      });
    });

    it('refuses a flap threshold above 100', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-data-processing-highFlapThreshold')
        .eq(1)
        .type('101')
        .blur();

      cy.contains(labelMustBeAPercentage).should('be.visible');
    });

    it('offers only the cloud data processing settings on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findByTestId('host-form-data-processing-checkFreshness').should(
        'exist'
      );
      cy.findAllByTestId('host-form-data-processing-freshnessThreshold')
        .eq(1)
        .should('exist');
      cy.findByTestId('host-form-data-processing-eventHandlerEnabled').should(
        'exist'
      );
      cy.findByTestId('host-form-data-processing-eventHandler').should('exist');

      [
        'acknowledgmentTimeout',
        'flapDetectionEnabled',
        'lowFlapThreshold',
        'highFlapThreshold',
        'eventHandlerArgs'
      ].forEach((field) => {
        cy.findByTestId(`host-form-data-processing-${field}`).should(
          'not.exist'
        );
      });
    });

    it('opens an existing host on its extended infos and severity', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.waitForRequest('@getHost');

      cy.findAllByTestId('host-form-extended-infos-note')
        .eq(1)
        .should('have.value', 'Front web server');
      // Left out of the response, so still empty.
      cy.findAllByTestId('host-form-extended-infos-noteUrl')
        .eq(1)
        .should('have.value', '');
      cy.findAllByTestId('host-form-extended-infos-actionUrl')
        .eq(1)
        .should('have.value', 'https://example.com/actions/host-0');
      cy.findAllByTestId('host-form-extended-infos-geoCoordinates')
        .eq(1)
        .should('have.value', '48.8566,2.3522');
      cy.findAllByTestId('host-form-extended-infos-altIcon')
        .eq(1)
        .should('have.value', '');
      cy.findAllByTestId('host-form-extended-infos-comment')
        .eq(1)
        .should('have.value', 'Racked in room B');
      cy.findByTestId('host-form-extended-infos-icon').should(
        'have.value',
        'server.png'
      );
      cy.findByTestId('host-form-extended-infos-icon-preview')
        .find('img')
        .should('have.attr', 'alt', 'server.png');
      cy.findByTestId('host-form-severity').should('have.value', 'Minor');
    });

    it('opens a host with no extended infos on empty fields', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 1').click();

      cy.waitForRequest('@getHost1');

      cy.findAllByTestId('host-form-name').eq(1).should('have.value', 'host 1');
      cy.findAllByTestId('host-form-extended-infos-note')
        .eq(1)
        .should('have.value', '');
      cy.findByTestId('host-form-extended-infos-icon').should('have.value', '');
      cy.findByTestId('host-form-extended-infos-icon-preview')
        .find('img')
        .should('not.exist');
      cy.findByTestId('host-form-severity').should('have.value', '');
    });

    it('creates a host with its extended infos and severity', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-name').eq(1).type('srv-apache-02');
      cy.findAllByTestId('host-form-address').eq(1).type('10.0.0.42');

      cy.findByTestId('host-form-poller').click();
      cy.get('.MuiAutocomplete-popper').contains('Poller EU').click();

      cy.findAllByTestId('host-form-extended-infos-note')
        .eq(1)
        .type('  Front web server  ');
      cy.findAllByTestId('host-form-extended-infos-noteUrl')
        .eq(1)
        .type('https://example.com/notes');
      cy.findAllByTestId('host-form-extended-infos-actionUrl')
        .eq(1)
        .type('https://example.com/actions');
      cy.findAllByTestId('host-form-extended-infos-geoCoordinates')
        .eq(1)
        .type('-33.8688,151.2093');
      cy.findAllByTestId('host-form-extended-infos-altIcon')
        .eq(1)
        .type('Web server');
      cy.findAllByTestId('host-form-extended-infos-comment')
        .eq(1)
        .type('Racked in room B');

      cy.findByTestId('host-form-extended-infos-icon').click();
      // The host-scoped selector, granted by host write access.
      cy.waitForRequest('@getFormMedias').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/medias'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('router.png').click();
      cy.findByTestId('host-form-extended-infos-icon-preview')
        .find('img')
        .should('have.attr', 'alt', 'router.png');

      cy.findByTestId('host-form-severity').click();
      cy.waitForRequest('@getFormHostSeverities').then(({ request }) => {
        expect(request.url.pathname).to.contain(
          '/api/configuration/hosts/host_severities'
        );
      });
      cy.get('.MuiAutocomplete-popper').contains('Critical').click();

      cy.get(`button[data-testid="${panelDataTestIds.save}"]`).click();

      cy.waitForRequest('@createHost').then(({ request }) => {
        expect(request.body.extended_informations).to.deep.equals({
          action_url: 'https://example.com/actions',
          alt_icon: 'Web server',
          comment: 'Racked in room B',
          geo_coordinates: '-33.8688,151.2093',
          icon_id: 13,
          note: 'Front web server',
          note_url: 'https://example.com/notes'
        });
        // A field of the host, not of its extended informations.
        expect(request.body.severity_id).to.equal(1);
      });
    });

    it('refuses geographic coordinates out of range', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.findAllByTestId('host-form-extended-infos-geoCoordinates')
        .eq(1)
        .type('91,2')
        .blur();

      cy.contains(labelInvalidGeographicCoordinates).should('be.visible');
    });

    it('offers no alt icon nor comments on a cloud platform', () => {
      initialize({ isCloudPlatform: true });

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      ['note', 'noteUrl', 'actionUrl', 'geoCoordinates'].forEach((field) => {
        cy.findAllByTestId(`host-form-extended-infos-${field}`)
          .eq(1)
          .should('exist');
      });
      cy.findByTestId('host-form-extended-infos-icon').should('exist');
      cy.findByTestId('host-form-severity').should('exist');

      ['altIcon', 'comment'].forEach((field) => {
        cy.findByTestId(`host-form-extended-infos-${field}`).should(
          'not.exist'
        );
      });
    });
  });
};
