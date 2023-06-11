import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkServicesAreMonitored,
  submitResultsViaClapi,
  updateFixturesResult
} from '../../../commons';
import {
  actionBackgroundColors,
  hostInAcknowledgementName,
  insertDtResources,
  secondServiceInDtName,
  serviceInAcknowledgementName,
  serviceInDtName,
  tearDownResource
} from '../common';

const chosenTZ = 'Africa/Casablanca';

const convert12hFormatToDate = (timeString: string): Date => {
  const currentDate = new Date();
  const dateString = currentDate.toLocaleDateString('en-US', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });

  const dateTimeString = `${dateString} ${timeString}`;

  return new Date(dateTimeString);
};

const calculateMinuteInterval = (startDate: Date, endDate: Date): number => {
  const diffInMilliseconds = endDate.getTime() - startDate.getTime();
  const minutes = Math.abs(Math.floor(diffInMilliseconds / 60000));

  return minutes;
};

before(() => {
  cy.startWebContainer({
    version: 'MON-19301-profile-timezone-automated'
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/downtime'
  }).as('postSaveDowntime');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timezone&action=list*'
  }).as('getTimezonesList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/monitoring/hosts/*/services/*/acknowledgements?limit=1'
  }).as('getAckToolTip');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_configuration_service&action=list&e=enable&page_limit=60&page=1'
  }).as('getServices');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/monitoring/resources*'
  }).as('getMonitoredResource');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/monitoring/hosts/*/services/*/metrics/performance*'
  }).as('getMonitoredMetrics');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/acknowledge'
  }).as('postAcknowledgments');
});

Given('a user authenticated in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });
});

Given('the platform is configured with at least one resource', () => {
  cy.reload();

  insertDtResources();

  checkServicesAreMonitored([
    {
      name: serviceInDtName
    },
    {
      name: secondServiceInDtName
    },
    {
      name: serviceInAcknowledgementName
    }
  ]);

  cy.get('[aria-label="Add columns"]').click();

  cy.get('li[role="menuitem"][value="State"]').click();

  cy.get('[aria-label="Add columns"]').click();

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();
});

When('the user clicks on Timezone field in his profile menu', () => {
  cy.navigateTo({
    page: 'My Account',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  }).wait('@getTimeZone');

  cy.getIframeBody()
    .find('span[aria-labelledby="select2-contact_location-container"]')
    .eq(0)
    .as('timezoneInput')
    .should('be.visible');

  cy.get('@timezoneInput').click().wait('@getTimezonesList');
});

When('the user selects a Timezone \\/ Location', () => {
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .clear()
    .type(chosenTZ)
    .wait('@getTimezonesList');

  cy.getIframeBody()
    .find('ul[id="select2-contact_location-results"] li')
    .contains(chosenTZ)
    .eq(0)
    .click();
});

When('the user saves the form', () => {
  cy.getIframeBody()
    .find('input[name="submitC"]')
    .eq(0)
    .contains('Save')
    .click();
});

Then('timezone information are updated on the banner', () => {
  cy.reload()
    .wait('@getTimeZone')
    .then(() => {
      cy.getTimeFromHeader().then((localTime: string) => {
        const timeofTZ = new Date().toLocaleTimeString('en-US', {
          hour: 'numeric',
          minute: '2-digit',
          timeZone: chosenTZ
        });

        expect(
          calculateMinuteInterval(
            convert12hFormatToDate(localTime),
            convert12hFormatToDate(timeofTZ)
          )
        ).to.be.lte(2);
      });
    });
});

Then("new timezone information is displayed in user's profile menu", () => {
  cy.getIframeBody()
    .find('span[aria-labelledby="select2-contact_location-container"]')
    .eq(0)
    .should('contain.text', chosenTZ);

  cy.logout();

  tearDownResource();
});

Given('a user with a custom timezone set in his profile', () => {
  cy.navigateTo({
    page: 'My Account',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  }).wait('@getTimeZone');

  cy.getIframeBody()
    .find('span[aria-labelledby="select2-contact_location-container"]')
    .eq(0)
    .should('contain.text', chosenTZ);
});

When('the user creates a downtime on a resource', () => {
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });

  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByTestId({ testId: 'Multiple Set Downtime' }).last().click();

  cy.getByLabel({ label: 'Set downtime' }).last().click();

  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(serviceInDtName))
        .parent()
        .then((val) => {
          return (
            val.css('background-color') === actionBackgroundColors.inDowntime
          );
        });
    },
    {
      timeout: 15000
    }
  );
});

Then(
  'date and time fields should be based on the custom timezone of the user',
  () => {
    cy.contains(serviceInDtName).parent().click();

    cy.get('p[data-testid="From_date"]').then(($toDate) => {
      cy.getTimeFromHeader().then((localTime: string) => {
        const toDate = $toDate[0].textContent || '';

        expect(
          calculateMinuteInterval(
            convert12hFormatToDate(localTime),
            new Date(toDate)
          )
        ).to.be.lte(2);
      });
    });

    cy.logout();

    tearDownResource();
  }
);

When('the user creates an acknowledgement on a resource', () => {
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });

  updateFixturesResult().then((submitResult) => {
    submitResultsViaClapi(submitResult).then(() => {
      cy.waitUntil(
        () => {
          return cy
            .refreshListing()
            .then(() => cy.contains(serviceInAcknowledgementName))
            .parent()
            .parent()
            .then((val) => {
              return val.children()[7].textContent === 'submit_status_2';
            });
        },
        {
          timeout: 30000
        }
      );
    });
  });

  cy.contains(serviceInAcknowledgementName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByLabel({ label: 'Acknowledge' }).last().click();

  cy.get('button').contains('Acknowledge').click();

  cy.wait('@postAcknowledgments').then(() => {
    cy.contains('Acknowledge command sent').should('have.length', 1);
  });

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(serviceInAcknowledgementName))
        .parent()
        .then((val) => {
          return (
            val.css('background-color') === actionBackgroundColors.acknowledge
          );
        });
    },
    {
      timeout: 30000
    }
  );
});

Then(
  'date and time fields of acknowledge resource should be based on the custom timezone of the user',
  () => {
    cy.get(`span[aria-label="${serviceInAcknowledgementName} Acknowledged"]`)
      .trigger('mouseover')
      .wait('@getAckToolTip');

    cy.get('div[role="tooltip"]')
      .eq(1)
      .find('td')
      .eq(1)
      .then(($date) => {
        cy.getTimeFromHeader().then((localTime: string) => {
          const toDate = $date[0].textContent || '';

          expect(
            calculateMinuteInterval(
              convert12hFormatToDate(localTime),
              new Date(toDate)
            )
          ).to.be.lte(2);
        });
      });

    tearDownResource();
  }
);

When('the user creates a downtime on a resource in Monitoring>Downtime', () => {
  cy.navigateTo({
    page: 'Downtimes',
    rootItemNumber: 1,
    subMenu: 'Downtimes'
  }).wait('@getTimeZone');

  cy.getIframeBody().contains('Add a downtime').click();

  cy.wait('@getTimeZone');

  cy.getIframeBody().find('label[for="service"]').click();

  cy.getIframeBody().find('.select2-container').eq(2).click();

  cy.wait('@getServices');

  cy.getIframeBody()
    .find('ul[id="select2-service_id-results"] li')
    .contains(serviceInDtName)
    .eq(0)
    .click();

  cy.getIframeBody()
    .find('input[name="submitA"]')
    .eq(0)
    .contains('Save')
    .click()
    .wait('@getTimeZone');
});

Then(
  'date and time fields should be based on the custom timezone of the user in Monitoring>Downtime',
  () => {
    cy.waitUntil(
      () => {
        return cy
          .reload()
          .then(() =>
            cy.getIframeBody().find('.ListTable tr:not(.ListHeader)').first()
          )
          .children()
          .then((val) => {
            return val.text().trim() !== 'No downtime scheduled';
          });
      },
      {
        timeout: 15000
      }
    );

    cy.wait('@getTimeZone');

    cy.getIframeBody()
      .find('.ListTable tr:not(.ListHeader) td')
      .eq(3)
      .then(($el) => {
        cy.getTimeFromHeader().then((localTime: string) => {
          const dtTime = $el[0].textContent || '';

          expect(
            calculateMinuteInterval(
              convert12hFormatToDate(localTime),
              new Date(dtTime)
            )
          ).to.be.lte(2);
        });
      });

    tearDownResource();
  }
);

When('the user opens a chart from resource status detail panel', () => {
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(hostInAcknowledgementName))
        .parent()
        .parent()
        .find('.MuiChip-label')
        .eq(0)
        .then((val) => {
          return val[0].textContent === 'Up';
        });
    },
    {
      timeout: 15000
    }
  );

  cy.contains(hostInAcknowledgementName)
    .parent()
    .click()
    .wait('@getMonitoredResource');

  cy.getByLabel({ label: 'Graph', tag: 'button' })
    .eq(2)
    .click()
    .wait('@getMonitoredResource');
});

When(
  'the user selects a date and time in the graph tab of detail panel',
  () => {
    cy.getByLabel({ label: 'Last day' }).click().wait('@getMonitoredMetrics');
  }
);

Then(
  'the time window of the chart is based on the custom timezone of the user',
  () => {
    cy.get('.visx-group').eq(0).trigger('mouseover', 0, 400);
  }
);

after(() => {
  cy.stopWebContainer();
});
