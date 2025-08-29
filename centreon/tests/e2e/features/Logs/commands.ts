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

  findTableData()
    .eq(2)
    .invoke('text')
    .should('include', thirdTd);
});

Cypress.Commands.add('submitResult', (service: string, status:string) => {
	cy.visit("/centreon/monitoring/resources");
	cy.get('input[placeholder="Search"]').clear().type("{enter}");
	cy.wait("@getResources");
	cy.getByLabel({ label: "Refresh" }).click();
	cy.wait("@getResources");
	cy.get('input[placeholder="Search"]').clear().type(`${service}{enter}`);
	cy.wait("@getResources");
	cy.getByLabel({ label: "Refresh" }).click();
	cy.wait("@getResources");
	cy.getByLabel({ label: "Select all" }).click();
	cy.get("button#Moreactions").click();
	cy.getByTestId({ testId: "Submit a status" }).click({ force: true });
	cy.contains("div", "Ok").click();
	cy.contains("p", status).click();
	cy.getByLabel({ label: "Output" }).type("Output");
	cy.getByLabel({ label: "Performance data" }).type("Performance data");
	cy.get('button[data-testid="Confirm"]').should("not.be.disabled").click();
  cy.wait("@getResources");
  cy.getByLabel({ label: "Refresh" }).click();
});

Cypress.Commands.add("selectHostAndCheckService", (serviceName: string) => {
  function selectHostFilter() {
    cy.getIframeBody()
      .find('select[id="host_filter"]')
      .siblings("span.select2-container")
      .click();

    cy.getIframeBody()
      .find("button.btc.bt_info")
      .contains("Select all")
      .click();

    cy.getIframeBody().find("button.btc.bt_success").contains("Ok").click();
    cy.getIframeBody()
      .find('input.bt_success[name="graph"][value="Apply period"]')
      .should('be.visible')
      .click();
  }

  selectHostFilter();

  cy.waitUntil(
    () =>
      cy
        .getIframeBody()
        .find("a") // récupérer tous les liens <a>
        .then(($links) => {
          const found = Array.from($links).some(
            (el) =>
              el.textContent?.trim() === serviceName &&
              Cypress.$(el).is(":visible"),
          );
          if (found) {
            return true;
          } else {
            cy.reload();
            selectHostFilter();
            return false;
          }
        }),
    {
      timeout: 120000,
      interval: 3000,
      errorMsg: `Le service "${serviceName}" n'est pas visible après plusieurs tentatives`,
    },
  );
});

interface submitResult {
  service: string;
  status: string;
}

interface IDyas {
    day: number,
    time_range: string,
}

interface IEDyas {
    day_range: string,
    time_range: string,
}

interface TimePeriod {
  name: string,
  alias: string,
  days: IDyas[],
  templates: number[],
  exceptions: IEDyas[],
}

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      addTimePeriodViaApi: (body: TimePeriod) => Cypress.Chainable;
      updateTimePeriodViaApi: (
        name: string,
        body: TimePeriod,
      ) => Cypress.Chainable;
      deleteTimePeriodViaApi: (name: string) => Cypress.Chainable;
      checkLogDetails: (
        tableIndex: number,
        trIndex: number,
        firstTd: string,
        secondTd: string,
        thirdTd: string,
      ) => Cypress.Chainable;
      addSubjectViaAPIv2: (payload: any, url: string) => Cypress.Chainable;
      deleteSubjectViaAPIv2: (url: string) => Cypress.Chainable;
      updateSubjectViaAPIv2: (payload: any, url: string) => Cypress.Chainable;
      submitResult: (service: string, status: string) => Cypress.Chainable;
      checkLogDetails: (
        tableIndex: number,
        trIndex: number,
        firstTd: string,
        secondTd: string,
        thirdTd: string,
      ) => Cypress.Chainable;
      addSubjectViaApiV2: (
        payload: Record<string, unknown>,
        url: string,
      ) => Cypress.Chainable;
      deleteSubjectViaApiV2: (url: string) => Cypress.Chainable;
      updateSubjectViaApiV2: (
        payload: Record<string, unknown>,
        url: string,
      ) => Cypress.Chainable;
      selectHostAndCheckService(serviceName: string): Chainable<void>;
    }
  }
}

export type {};
