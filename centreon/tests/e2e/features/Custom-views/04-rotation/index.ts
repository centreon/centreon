import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { buildRotationTimerQuery, visitCustomViewPage } from '../common';

const adminLogin = 'admin';
// The rotation timer starts once the page applied it: allow for the request
// round trip and the 250 ms polling on top of the interval
const earlySwitchTolerance = 1_500;
const lateSwitchTolerance = 4_000;
const activeViewSelector = '#tabs .tabs_header > li.ui-tabs-active > a';

let views: Array<string> = [];
let rotationSeconds = 0;
let rotationAppliedAt = 0;

beforeEach(() => {
  cy.startContainers();

  views = [];
  rotationSeconds = 0;
  rotationAppliedAt = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.customViews_views}?currentView=*`
  }).as('getViews');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.pages.customViews_action
  }).as('action');
});

afterEach(() => {
  cy.stopContainers();
});

// Same request as the "Add view" form of the page
const createCustomView = (name: string): void => {
  cy.request({
    body: {
      action: 'add',
      'create_load[create_load]': 'create',
      'layout[layout]': 'column_1',
      name,
      public: 0
    },
    form: true,
    method: 'POST',
    url: INTERCEPTORS.pages.customViews_action
  }).then(({ body, status }) => {
    expect(status).to.eq(200);
    expect(String(body)).to.include('custom_view_id');
  });
};

const activeView = (): Cypress.Chainable<string> =>
  cy
    .getIframeBody()
    .find(activeViewSelector)
    .invoke('text')
    .then((text) => cy.wrap(text.trim(), { log: false }));

// Waits for the view to become the displayed one; yields the time it did
const waitForActiveView = (
  name: string,
  timeout: number
): Cypress.Chainable<number> =>
  cy
    .waitUntil(() => activeView().then((active) => active === name), {
      errorMsg: `The view ${name} was not displayed in time`,
      interval: 250,
      timeout
    })
    .then(() => cy.wrap(Date.now(), { log: false }));

const expectSwitchDelay = (from: number, to: number): void => {
  const delay = to - from;
  const interval = rotationSeconds * 1000;

  expect(delay, 'delay before the next view in ms').to.be.within(
    interval - earlySwitchTolerance,
    interval + lateSwitchTolerance
  );
};

const otherView = (name: string): string =>
  views.find((view) => view !== name) as string;

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'the user owns two custom views {string} and {string}',
  (first: string, second: string) => {
    views = [first, second];
    views.forEach(createCustomView);

    visitCustomViewPage();
    cy.wait('@getViews');
    views.forEach((view) => {
      cy.waitForElementInIframe('#main-content', `a:contains("${view}")`);
    });
    activeView().should('be.oneOf', views);
  }
);

When(
  'the user sets the rotation to {int} seconds and applies it',
  (seconds: number) => {
    rotationSeconds = seconds;

    cy.getIframeBody().find('button.setRotate').click();
    cy.getIframeBody().find('#rotationTabs').should('be.visible');

    // The slider moves by steps of 5 seconds from 0
    cy.getIframeBody()
      .find('#rotation_timer .ui-slider-handle')
      .type(`{home}${'{rightarrow}'.repeat(seconds / 5)}`);
    cy.getIframeBody()
      .find('#timer_value')
      .should('have.text', `${seconds} seconds`);

    cy.getIframeBody()
      .find('#rotationTabs input[type="button"][value="Apply"]')
      .click();
    cy.wait('@action').then(({ request }) => {
      expect(String(request.body)).to.include('action=setRotate');
      expect(String(request.body)).to.include(`timer=${seconds}`);
      rotationAppliedAt = Date.now();
    });
  }
);

Then(
  'the displayed view switches to the other view within {int} seconds and back after another {int} seconds',
  (firstDelay: number, secondDelay: number) => {
    expect([firstDelay, secondDelay]).to.deep.eq([
      rotationSeconds,
      rotationSeconds
    ]);

    const timeout = rotationSeconds * 1000 + lateSwitchTolerance;

    activeView().then((initialView) => {
      waitForActiveView(otherView(initialView), timeout).then(
        (firstSwitchAt) => {
          expectSwitchDelay(rotationAppliedAt, firstSwitchAt);

          waitForActiveView(initialView, timeout).then((secondSwitchAt) => {
            expectSwitchDelay(firstSwitchAt, secondSwitchAt);
          });
        }
      );
    });
  }
);

Then('the rotation interval is kept after the page is reloaded', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildRotationTimerQuery(adminLogin)
  }).then(([rows]) => {
    expect(rows, 'saved rotation interval').to.have.length(1);
    expect(Number(rows[0].cp_value)).to.eq(rotationSeconds);
  });

  cy.reload();
  cy.wait('@getViews');

  cy.getIframeBody()
    .find('#timer_value')
    .should('have.text', `${rotationSeconds} seconds`);
});
