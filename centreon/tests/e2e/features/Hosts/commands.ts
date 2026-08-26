import { PAGES } from 'fixtures/shared/constants/pages';

import {
  confirmModalSelectors,
  formSelectors,
  getListingRow,
  listingSelectors
} from './common';

Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.getIframeBody(iframeSelector).then(($iframeBody) => {
          const element = $iframeBody.find(elementSelector);

          return element.length > 0 && element.is(':visible');
        }),
      {
        errorMsg: `Element ${elementSelector} not found in iframe ${iframeSelector} after waiting`,
        interval: 1000,
        timeout: 60000
      }
    );
  }
);

Cypress.Commands.add('checkLegacyRadioButton', (label: string) => {
  cy.getIframeBody()
    .contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody()
        .find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');
    });
});

Cypress.Commands.add(
  'updateHostGroupViaApi',
  (body: HostGroup, hostGroupName: string) => {
    const query = `SELECT h.hg_id from hostgroup as h WHERE h.hg_name='${hostGroupName}'`;
    cy.requestOnDatabase({
      database: 'centreon',
      query
    }).then(([rows]) => {
      cy.request({
        body: body,
        method: 'PUT',
        url: `/centreon/api/beta/configuration/hosts/groups/${rows[0].hg_id}`
      }).then((response) => {
        expect(response.status).to.eq(204);
      });
    });
  }
);

Cypress.Commands.add('addHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody().find(`div[title="${body.dependentHostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(2).click();
  cy.getIframeBody().find(`div[title="${body.dependentServices[0]}"]`).click();
  cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('updateHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .clear()
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="eUp"]').click({ force: true });

  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('label[for="nDown"]').click({ force: true });
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[1]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody().find(`div[title="${body.dependentHostNames[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .type(body.dependentServices[0]);
  cy.getIframeBody()
    .find(`div[title="host2 - ${body.dependentServices[0]}"]`)
    .click();
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .clear()
    .type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('addHostGroupDependency', (body: HostGroupDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostGroupsNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody()
    .find(`div[title="${body.dependentHostGroupsNames[0]}"]`)
    .click();
  cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add(
  'updateHostGroupDependency',
  (body: HostGroupDependency) => {
    cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
    cy.getIframeBody()
      .find('input[name="dep_description"]')
      .clear()
      .type(body.description);
    cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
    cy.getIframeBody().find('label[for="eUp"]').click({ force: true });

    cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
    cy.getIframeBody().find('label[for="nDown"]').click({ force: true });
    cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.hostGroupsNames[0]}"]`).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.hostGroupsNames[1]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .type(body.dependentHostGroupsNames[0]);
    cy.getIframeBody()
      .find(`div[title="${body.dependentHostGroupsNames[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
  }
);

Cypress.Commands.add('lockHostTemplateWithSql', (name: string) => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE host SET host_locked = 1 WHERE host_name = "${name}"`
  }).then(([result]) => {
    // An UPDATE resolves to an OK packet, not a row array, so the previous check
    // on .length could never fire: a typo in the name silently left the template
    // unlocked and failed a later step for an unrelated reason.
    if (!result || result.affectedRows === 0) {
      throw new Error(`Host template not found for template name ${name}`);
    }
  });
});

Cypress.Commands.add('setIconWithSql', (name: string) => {
  // The icon column is the one value the listing computes rather than reads:
  // HostIconResolver walks host_template_relation for it. Asserting on it means
  // an object has to actually carry an icon, and no other suite sets one. Any
  // media reachable through a directory does — the platform ships at least one.

  // The row comes with the object in the normal flow, but a template shipped
  // with the platform can predate it: insert it when missing so the update
  // below has something to write on.
  cy.requestOnDatabase({
    database: 'centreon',
    query: `INSERT INTO extended_host_information (host_host_id)
            SELECT h.host_id
            FROM host h
            WHERE h.host_name = "${name}"
              AND NOT EXISTS (
                SELECT 1 FROM extended_host_information e
                WHERE e.host_host_id = h.host_id
              )`
  });

  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE extended_host_information ehi
            INNER JOIN host h ON h.host_id = ehi.host_host_id
            SET ehi.ehi_icon_image = (
              SELECT vi.img_id
              FROM view_img vi
              INNER JOIN view_img_dir_relation vidr ON vidr.img_img_id = vi.img_id
              INNER JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
              WHERE vid.dir_alias <> '' AND vi.img_path <> ''
              ORDER BY vi.img_id
              LIMIT 1
            )
            WHERE h.host_name = "${name}"`
  });

  // Read the path back and hand it to the caller: asserting the exact src the
  // row must show is what proves the resolver builds it from dir_alias and
  // img_path the way the legacy helper did. Reading it also catches a fixture
  // that silently wrote nothing, which would otherwise surface as the row
  // falling back to its inline glyph.
  return cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT CONCAT('./img/media/', vid.dir_alias, '/', vi.img_path) AS icon_path
              FROM extended_host_information ehi
              INNER JOIN host h ON h.host_id = ehi.host_host_id
              INNER JOIN view_img vi ON vi.img_id = ehi.ehi_icon_image
              INNER JOIN view_img_dir_relation vidr ON vidr.img_img_id = vi.img_id
              INNER JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
              WHERE h.host_name = "${name}"`
    })
    .then(([rows]) => {
      const iconPath = rows[0]?.icon_path;
      if (!iconPath) {
        throw new Error(`No icon could be set on ${name}`);
      }

      return cy.wrap(iconPath, { log: false });
    });
});

Cypress.Commands.add('visitHostsListingPage', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
});

// ---------------------------------------------------------------------------
// Modernized listing commands (hosts and host templates)
// ---------------------------------------------------------------------------

const openListing = (url: string, alias: string): void => {
  cy.visit(url);
  cy.wait('@getTimeZone');
  // Consume the listing fetch this visit triggers. Left in the queue it would
  // be handed to the next cy.wait(alias) — the one meant to await a search or a
  // filter — which would then assert against the rows from before that action,
  // since listing.js only re-renders "Loading..." on the first fetch.
  cy.wait(alias);
  cy.waitForElementInIframe('#main-content', listingSelectors.table);
  // Wait on the "Loading..." row disappearing, not on row checkboxes: the table
  // and that row are rendered server-side, so counting <tr> is satisfied before
  // the fetch has landed, while requiring a checkbox rejects a listing that
  // legitimately comes back empty — "No results found" carries none either.
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr td`)
    .should('not.contain', 'Loading');
};

Cypress.Commands.add('openHostsListing', () => {
  openListing(PAGES.configuration.hostsLegacy, '@getHostListing');
});

Cypress.Commands.add('openHostTemplatesListing', () => {
  openListing(
    PAGES.configuration.hostsTemplatesLegacy,
    '@getHostTemplateListing'
  );
});

/**
 * The add/edit form opens in a side panel, which is an iframe nested inside the
 * page iframe — getIframeBody() alone stops at the outer one.
 */
Cypress.Commands.add('getSidePanelBody', () => {
  return cy
    .getIframeBody()
    .find(formSelectors.sidePanelFrame)
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openListingRowForm', (name: string) => {
  // Scoped to the name column, like getListingRow: the Templates column renders
  // parent template names as links of their own, so an unscoped lookup can open
  // the wrong object — and the host_name assertion below would still pass.
  // Same substring caveat as getListingRow.
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr td:nth-child(2)`)
    .contains('a', name)
    .click();

  // `exist`, not `be.visible`: a locked object opens the form frozen, and
  // QuickForm renders a frozen text element as `<input type="hidden">`. Asserting
  // visibility passes on an editable form and fails on every read-only one —
  // which is precisely the case the freeze feature exists for.
  cy.getSidePanelBody()
    .find('input[name="host_name"]', { timeout: 20_000 })
    .should('exist');
});

/**
 * Expands a collapsible form section by id (see formSections in common.ts).
 *
 * The migrated form ships its secondary sections with the `collapsed` class, so
 * their body is display:none and Cypress refuses to type into a field inside
 * one. Idempotent — an already-open section is left alone, so a step can call
 * this without knowing the current state.
 */
Cypress.Commands.add('expandFormSection', (sectionId: string) => {
  const clickHeaderWhileCollapsed = (): void => {
    cy.getSidePanelBody().then(($body) => {
      if ($body.find(`#${sectionId}.collapsed`).length === 0) {
        return;
      }
      cy.getSidePanelBody().find(`#${sectionId} .cf-section-header`).click();
    });
  };

  // Twice on purpose: a select2 left open by an earlier pick covers the form
  // with its mask, which swallows the first click whole — the header never sees
  // it and the section stays collapsed. Each call is a no-op once it is open.
  clickHeaderWhileCollapsed();
  clickHeaderWhileCollapsed();

  cy.getSidePanelBody()
    .find(`#${sectionId}`)
    .should('not.have.class', 'collapsed');
});

/**
 * The row checkbox is hidden behind its md-checkbox label, so it only takes a
 * forced click. Scoped through getListingRow so the name is matched in the name
 * column and not in a templates column that happens to quote it.
 */
Cypress.Commands.add('tickListingRow', (name: string) => {
  getListingRow(name).find(listingSelectors.rowCheckbox).click({ force: true });
});

/**
 * Opens the Mass Change side panel on the rows ticked so far. Unlike Delete and
 * Duplicate, Mass Change is not gated by the confirmation modal — picking the
 * menu item submits straight through.
 *
 * The panel cannot be awaited on `input[name="host_name"]` the way the edit form
 * is: name and alias are deliberately absent in mass change. A QuickForm rule
 * left declared on either of them fatals the whole panel, so assert the error
 * text is absent first — otherwise the failure reads as a timeout on the submit
 * button and says nothing about the cause.
 */
Cypress.Commands.add('openListingMassChange', () => {
  cy.getIframeBody().find(listingSelectors.moreActionsButton).click();
  cy.getIframeBody()
    .find(listingSelectors.moreActionsItem)
    .contains('Mass Change')
    .click({ force: true });

  cy.getSidePanelBody().should('not.contain', 'does not exist');
  cy.getSidePanelBody()
    .find(formSelectors.massChangeSubmit, { timeout: 20_000 })
    .should('be.visible');
});

/**
 * Runs a bulk action the way a user does: tick the row, open the custom
 * "More actions" menu, then confirm in the modal. Delete and Duplicate are
 * gated by that modal — re-wiring the hidden select's onchange would submit
 * straight through and leave the modal untested.
 */
Cypress.Commands.add(
  'runListingBulkAction',
  (name: string, action: string, expectedTitle: string) => {
    cy.tickListingRow(name);

    cy.getIframeBody().find(listingSelectors.moreActionsButton).click();
    cy.getIframeBody()
      .find(listingSelectors.moreActionsItem)
      .contains(action)
      .click({ force: true });

    cy.getIframeBody()
      .find(confirmModalSelectors.modal, { timeout: 10_000 })
      .should('be.visible');
    cy.getIframeBody()
      .find(confirmModalSelectors.title)
      .should('contain', expectedTitle);
    // The name (single selection) is interpolated into the message in bold.
    cy.getIframeBody()
      .find(`${confirmModalSelectors.body} strong`)
      .should('contain', name);
    cy.getIframeBody().find(confirmModalSelectors.confirm).click();
  }
);

interface HostGroup {
  name: string;
  alias: string;
  iconId: number;
  geoCoords: string;
  comment: string;
  isActivated: boolean;
}

interface HostDependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnDown: number;
  executionFailsOnUnreachable: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnDown: number;
  notificationFailsOnUnreachable: number;
  notificationFailsOnPending: number;
  hostNames: Array<string>;
  dependentHostNames: Array<string>;
  dependentServices: Array<string>;
  comment: string;
}

interface HostGroupDependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnDown: number;
  executionFailsOnUnreachable: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnDown: number;
  notificationFailsOnUnreachable: number;
  notificationFailsOnPending: number;
  hostGroupsNames: Array<string>;
  dependentHostGroupsNames: Array<string>;
  comment: string;
}

// ---------------------------------------------------------------------------
// Host categories commands
// ---------------------------------------------------------------------------

Cypress.Commands.add('openHostCategoriesListing', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');

  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Cypress.Commands.add('getHostCategorySidePanelBody', () => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openHostCategoryForm', (name: string) => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();

  cy.getHostCategorySidePanelBody()
    .find('input[name="hc_name"]', { timeout: 20000 })
    .should('be.visible');
});

Cypress.Commands.add(
  'selectHostCategoryFieldOption',
  (label: string, option: string) => {
    cy.getHostCategorySidePanelBody()
      .contains('.cf-field', label)
      .find('.select2-selection')
      .click({ force: true });

    cy.getHostCategorySidePanelBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(option)
      .click({ force: true });
  }
);

Cypress.Commands.add('createHostCategory', (body: Record<string, unknown>) => {
  cy.request({
    body,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/categories'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
});

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      checkLegacyRadioButton: (label: string) => Cypress.Chainable;
      updateHostGroupViaApi: (
        body: HostGroup,
        name: string
      ) => Cypress.Chainable;
      addHostDependency: (body: HostDependency) => Cypress.Chainable;
      updateHostDependency: (body: HostDependency) => Cypress.Chainable;
      addHostGroupDependency: (body: HostGroupDependency) => Cypress.Chainable;
      updateHostGroupDependency: (
        body: HostGroupDependency
      ) => Cypress.Chainable;
      lockHostTemplateWithSql: (name: string) => Cypress.Chainable;
      setIconWithSql: (name: string) => Cypress.Chainable;
      visitHostsListingPage: () => Cypress.Chainable;
      openHostsListing(): Chainable<void>;
      openHostTemplatesListing(): Chainable<void>;
      getSidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openListingRowForm(name: string): Chainable<void>;
      expandFormSection(sectionId: string): Chainable<void>;
      tickListingRow(name: string): Chainable<void>;
      openListingMassChange(): Chainable<void>;
      runListingBulkAction(
        name: string,
        action: string,
        expectedTitle: string
      ): Chainable<void>;
      openHostCategoriesListing(): Chainable<void>;
      getHostCategorySidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openHostCategoryForm(name: string): Chainable<void>;
      selectHostCategoryFieldOption(
        label: string,
        option: string
      ): Chainable<void>;
      createHostCategory(body: Record<string, unknown>): Chainable<void>;
    }
  }
}
