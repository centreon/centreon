import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkServicesAreMonitored } from '../../../commons';
import {
  actionBackgroundColors,
  insertDtResources,
  secondServiceInDtName,
  serviceInDtName,
  tearDownResource
} from '../common';

const chosenTZ = 'Africa/Casablanca';

const addSecondsToStringTime = ({ time, secondsToAdd }): string => {
  const date = new Date(`2000/01/01 ${time}`);

  date.setSeconds(date.getSeconds() + secondsToAdd);

  const updatedTimeString = date.toLocaleTimeString([], {
    hour: 'numeric',
    minute: '2-digit'
  });

  return updatedTimeString;
};

before(() => {
  cy.startWebContainer();
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
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

Given('a user authenticated in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });
});

Given('the platform is configured with at least one resource', () => {
  insertDtResources();

  checkServicesAreMonitored([
    {
      name: serviceInDtName
    },
    {
      name: secondServiceInDtName
    }
  ]);
});

When('user cliks on Timezone field in his profile menu', () => {
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

When('user selects a Timezone \\/ Location', () => {
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

When('user saves the form', () => {
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
        expect(localTime).to.equal(timeofTZ);
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

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();

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

    cy.get('p[data-testid="To_date"]').then(($toDate) => {
      const toDate = $toDate[0].textContent;

      const timeRegex = /(\d{1,2}:\d{2} [AP]M)$/;

      const extractedTime = toDate?.match(timeRegex);

      const Totime = extractedTime ? extractedTime[0] : '';

      cy.getTimeFromHeader().then((localTime: string) => {
        expect(
          addSecondsToStringTime({ secondsToAdd: 3600, time: localTime })
        ).to.equal(Totime);
      });
    });

    tearDownResource();
  }
);

When('the user creates an acknowledgement on a resource', () => {
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();

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
