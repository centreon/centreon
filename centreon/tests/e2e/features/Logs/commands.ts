interface Days {
  day: number;
  timeRange: string;
}

interface ExceptionDays {
  dayRange: string;
  timeRange: string;
}

interface TimePeriod {
  name: string;
  alias: string;
  days: Array<Days>;
  templates: Array<number>;
  exceptions: Array<ExceptionDays>;
}

Cypress.Commands.add('addTimePeriodViaApi', (payload: TimePeriod) => {
  const apiPayload = {
    ...payload,
    days: payload.days.map((day) => ({
      day: day.day,
      time_range: day.timeRange
    }))
  };

  cy.request({
    body: apiPayload,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/latest/configuration/timeperiods'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
});

Cypress.Commands.add(
  'addSubjectViaApiV2',
  (payload: Record<string, unknown>, url: string) => {
    cy.request({
      body: payload,
      headers: {
        'Content-Type': 'application/json'
      },
      method: 'POST',
      url: url
    }).then((response) => {
      expect(response.status).to.eq(201);
    });
  }
);

Cypress.Commands.add(
  'updateTimePeriodViaApi',
  (name: string, payload: TimePeriod) => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: `SELECT * FROM timeperiod WHERE tp_name='${name}'`
    }).then(([rows]) => {
      const id = rows[0].tp_id;
      const apiPayload = {
        ...payload,
        days: payload.days.map((day) => ({
          day: day.day,
          time_range: day.timeRange
        }))
      };
      cy.request({
        body: apiPayload,
        headers: {
          'Content-Type': 'application/json'
        },
        method: 'PUT',
        url: `/centreon/api/latest/configuration/timeperiods/${id}`
      }).then((response) => {
        expect(response.status).to.eq(204);
      });
    });
  }
);

Cypress.Commands.add(
  'updateSubjectViaApiV2',
  (payload: Record<string, unknown>, url: string) => {
    cy.request({
      body: payload,
      headers: {
        'Content-Type': 'application/json'
      },
      method: 'PUT',
      url: url
    }).then((response) => {
      expect(response.status).to.eq(204);
    });
  }
);

Cypress.Commands.add('deleteTimePeriodViaApi', (name: string) => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT * FROM timeperiod WHERE tp_name='${name}'`
  }).then(([rows]) => {
    const id = rows[0].tp_id;
    cy.request({
      headers: {
        'Content-Type': 'application/json'
      },
      method: 'DELETE',
      url: `/centreon/api/latest/configuration/timeperiods/${id}`
    }).then((response) => {
      expect(response.status).to.eq(204);
    });
  });
});

Cypress.Commands.add('deleteSubjectViaApiV2', (url: string) => {
  cy.request({
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'DELETE',
    url: url
  }).then((response) => {
    expect(response.status).to.eq(204);
  });
});

/**
 * Open the object timeline (new detail page) by clicking the object name in the
 * listing, then expand the card whose modification-type badge matches
 * `badgeLabel` (Added / Changed / Deleted / ...). The card is aliased as
 * `@timelineCard` so subsequent `checkLogDetail` calls scope to it.
 */
Cypress.Commands.add('expandTimelineCard', (badgeLabel: string) => {
  cy.getIframeBody()
    .find('.cld-timeline .cld-card')
    .filter((_index, card) =>
      Cypress.$(card).find('.cld-card-badge').text().includes(badgeLabel)
    )
    .first()
    .as('timelineCard');

  cy.get('@timelineCard').find('.cld-card-header').click();
  cy.get('@timelineCard').find('.cld-diff-panel').should('have.class', 'open');
});

/**
 * Assert a single field row inside the currently expanded `@timelineCard` diff
 * table. Field-keyed (not row-indexed) so it is robust to field ordering and to
 * the Added shape, which has no "Before" column — pass '' as `before` there.
 */
Cypress.Commands.add(
  'checkLogDetail',
  (fieldName: string, before: string, after: string) => {
    cy.get('@timelineCard')
      .find('.cld-diff-table tbody tr')
      .filter(
        (_index, row) =>
          Cypress.$(row).find('td.cld-fname').text().trim() === fieldName
      )
      .first()
      .within(() => {
        cy.get('td.cld-fname').should('contain.text', fieldName);
        if (before !== '') {
          cy.get('td.cld-fbefore').should('contain.text', before);
        }
        cy.get('td.cld-fafter').should('contain.text', after);
      });
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      addTimePeriodViaApi: (body: TimePeriod) => Cypress.Chainable;
      updateTimePeriodViaApi: (
        name: string,
        body: TimePeriod
      ) => Cypress.Chainable;
      deleteTimePeriodViaApi: (name: string) => Cypress.Chainable;
      expandTimelineCard: (badgeLabel: string) => Cypress.Chainable;
      checkLogDetail: (
        fieldName: string,
        before: string,
        after: string
      ) => Cypress.Chainable;
      addSubjectViaApiV2: (
        payload: Record<string, unknown>,
        url: string
      ) => Cypress.Chainable;
      deleteSubjectViaApiV2: (url: string) => Cypress.Chainable;
      updateSubjectViaApiV2: (
        payload: Record<string, unknown>,
        url: string
      ) => Cypress.Chainable;
    }
  }
}

export type {};
