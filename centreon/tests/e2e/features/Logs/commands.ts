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
  days: Days[];
  templates: number[];
  exceptions: ExceptionDays[];
}

Cypress.Commands.add('addTimePeriodViaApi', (payload: TimePeriod) => {
  const apiPayload = {
    ...payload,
    days: payload.days.map((day) => ({
      day: day.day,
      // biome-ignore lint/style/useNamingConvention: API requires snake_case
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
          // biome-ignore lint/style/useNamingConvention: API requires snake_case
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

Cypress.Commands.add(
  'checkLogDetails',
  (
    tableIndex: number,
    trIndex: number,
    firstTd: string,
    secondTd: string,
    thirdTd: string
  ) => {
    const findTableData = (): Cypress.Chainable => {
      return cy
        .getIframeBody()
        .find('table.ListTable')
        .eq(tableIndex)
        .find('tbody tr')
        .eq(trIndex)
        .find('td')
        .then(cy.wrap);
    };

    findTableData().should('have.length', 3);

    findTableData().eq(0).invoke('text').should('include', firstTd);

    findTableData().eq(1).invoke('text').should('include', secondTd);

    findTableData().eq(2).invoke('text').should('include', thirdTd);
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
      checkLogDetails: (
        tableIndex: number,
        trIndex: number,
        firstTd: string,
        secondTd: string,
        thirdTd: string
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
