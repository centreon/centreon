Cypress.Commands.add('addTimePeriodViaApi', (payload: TimePeriod) => {
    cy.request({
        body: payload,
        headers: {
          'Content-Type': 'application/json'
        },
        method: 'POST',
        url: '/centreon/api/latest/configuration/timeperiods'
      }).then((response) => {
        expect(response.status).to.eq(201);
      });
});

Cypress.Commands.add('addSubjectViaAPIv2', (payload: any, url: string) => {
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

});

Cypress.Commands.add('updateTimePeriodViaApi', (name: string, payload: TimePeriod) => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT * FROM timeperiod WHERE tp_name='${name}'`
  }).then(([rows]) => {
    const id = rows[0].tp_id;
    cy.request({
      body: payload,
      headers: {
        'Content-Type': 'application/json'
      },
      method: 'PUT',
      url: `/centreon/api/latest/configuration/timeperiods/${id}`
    }).then((response) => {
      expect(response.status).to.eq(204);
    });
  });
});

Cypress.Commands.add('updateSubjectViaAPIv2', (payload: any, url: string) => {
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
});

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

Cypress.Commands.add('deleteSubjectViaAPIv2', (url: string) => {
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

Cypress.Commands.add('checkLogDetails',(tableIndex: number, trIndex:number, firstTd:string, secondTd:string, thirdTd:string) => {
  const findTableData = (): Cypress.Chainable => {
    return cy.getIframeBody()
      .find('table.ListTable')
      .eq(tableIndex)
      .find('tbody tr')
      .eq(trIndex)
      .find('td')
      .then(cy.wrap);
  };

  findTableData()
      .should('have.length', 3);

  findTableData()
     .eq(0)
     .invoke('text')
     .should('include', firstTd);

  findTableData()
    .eq(1)
    .invoke('text')
    .should('include', secondTd);

  findTableData()
    .eq(2)
    .invoke('text')
    .should('include', thirdTd);
});


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
  namespace Cypress {
    interface Chainable {
      addTimePeriodViaApi: (body: TimePeriod) => Cypress.Chainable;
      updateTimePeriodViaApi: (name: string, body: TimePeriod) => Cypress.Chainable;
      deleteTimePeriodViaApi: (name: string) => Cypress.Chainable;
      checkLogDetails: (tableIndex: number, trIndex:number, firstTd:string, secondTd:string, thirdTd:string) => Cypress.Chainable;
      addSubjectViaAPIv2: (payload: any, url: string) => Cypress.Chainable;
      deleteSubjectViaAPIv2: (url: string) => Cypress.Chainable;
      updateSubjectViaAPIv2: (payload: any, url: string) => Cypress.Chainable;
    }
  }
}

export {};