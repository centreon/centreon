/* eslint-disable cypress/no-unnecessary-waiting */
import { When, Then, Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  initializeConfigACLAndGetLoginPage,
  millisecondsValueForSixMonth,
  millisecondsValueForFourHour,
  checkDefaultsValueForm
} from '../common';
import { getUserContactId } from '../../../../commons';

before(() => {
  cy.startContainers().then(() => {
    return initializeConfigACLAndGetLoginPage();
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/configuration/users?page=1&sort_by=%7B%22alias%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%7B%22provider_name%22%3A%7B%22%24eq%22%3A%22local%22%7D%7D%5D%7D'
  }).as('getListContact');

  getUserContactId('user1')
    .as('user1Id')
    .then(() => {
      cy.get('@user1Id').then((userid) => {
        cy.requestOnDatabase({
          database: 'centreon',
          query: `SELECT creation_date FROM contact_password WHERE contact_id = '${userid}'`
        })
          .then(([rows]) => {
            if (rows.length === 0) {
              throw new Error(
                `Password creation date not found for contact id ${userid}`
              );
            }

            return rows[0].creation_date;
          })
          .as('user1CreationPasswordDate');
      });
    });

  getUserContactId('user2')
    .as('user2Id')
    .then(() => {
      cy.get('@user2Id').then((userid) => {
        cy.requestOnDatabase({
          database: 'centreon',
          query: `SELECT creation_date FROM contact_password WHERE contact_id = '${userid}'`
        })
          .then(([rows]) => {
            if (rows.length === 0) {
              throw new Error(
                `Password creation date not found for contact id ${userid}`
              );
            }

            return rows[0].creation_date;
          })
          .as('user2CreationPasswordDate');
      });
    });
});

Given('an administrator deploying a new Centreon platform', () =>
  cy
    .loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
    .wait('@getLastestUserFilters')
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
);

When('the administrator opens the authentication configuration menu', () => {
  cy.get('div[role="tablist"] button')
    .eq(0)
    .contains('Password security policy');
});

Then(
  'a default password policy and default excluded users must be present',
  () => {
    checkDefaultsValueForm.forEach(({ selector, value, custom }) => {
      cy.get(selector).should('exist').and('have.value', value);
      if (custom) {
        custom();
      }
    });
    cy.logout();
  }
);

Given(
  'an administrator configuring a Centreon platform and an existing user account',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: false })
      .wait('@getLastestUserFilters')
      .isInProfileMenu('Edit profile');

    cy.contains('Logout').click();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

When(
  'the administrator sets a valid password length and sets all the letter cases',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false })
      .wait('@getLastestUserFilters')
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      })
      .get('div[role="tablist"] button')
      .eq(0)
      .contains('Password security policy');

    cy.get('#Minimumpasswordlength').type('{selectall}{backspace}10');

    cy.get('#Passwordmustcontainlowercase').should(
      'have.class',
      'MuiButton-containedPrimary'
    );
    cy.get('#Passwordmustcontainuppercase').should(
      'have.class',
      'MuiButton-containedPrimary'
    );
    cy.get('#Passwordmustcontainnumbers').should(
      'have.class',
      'MuiButton-containedPrimary'
    );
    cy.get('#Passwordmustcontainspecialcharacters').should(
      'have.class',
      'MuiButton-containedPrimary'
    );

    cy.get('#Save').should('be.enabled').click();

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then(
  'the existing user can not define a password that does not match the password case policy defined by the administrator and is notified about it',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: false })
      .wait('@getLastestUserFilters')
      .isInProfileMenu('Edit profile')
      .should('be.visible');

    cy.visit('/centreon/main.php?p=50104&o=c').wait('@getTimeZone');

    cy.getIframeBody()
      .find('form')
      .within(() => {
        cy.get('#passwd1').should('be.visible').type('azerty');
        cy.get('#passwd2').should('be.visible').type('azerty');
      });

    cy.getIframeBody().find('#validForm input[name="submitC"]').click();

    cy.wait('@getTimeZone')
      .getIframeBody()
      .find('#Form')
      .find('#tab1')
      .parent()
      .contains(
        "Your password must be 10 characters long and must contain : uppercase characters, lowercase characters, numbers, special characters among '@$!%*?&'."
      );

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Given(
  'an administrator configuring a Centreon platform and an existing user account with password up to date',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
      .wait('@getLastestUserFilters')
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      });
  }
);

When(
  'the administrator sets valid password expiration policy durations in password expiration policy configuration and the user password expires',
  () => {
    cy.reload()
      .get('div[role="tablist"] button')
      .eq(0)
      .contains('Password security policy');

    cy.get('[data-testid="local_passwordExpirationMonths"]').parent().click();
    cy.get('ul li[data-value="0"]').click();
    cy.get('#Save').should('be.enabled').click();

    cy.get('@user1Id').then((idUser) => {
      cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
        const newDateOfCreationDate =
          Number(userPasswordCreationDate) - millisecondsValueForSixMonth;
        cy.requestOnDatabase({
          database: 'centreon',
          query: `UPDATE contact_password SET creation_date = '${newDateOfCreationDate}' WHERE contact_id = '${idUser}'`
        });
      });
    });

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then('the existing user can not authenticate and is notified about it', () => {
  cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: false })
    .url()
    .should('include', '/reset-password');

  cy.get('@user1Id').then((idUser) => {
    cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
      cy.requestOnDatabase({
        database: 'centreon',
        query: `UPDATE contact_password SET creation_date = '${userPasswordCreationDate}' WHERE contact_id = '${idUser}';`
      });
    });
  });

  cy.visit('/centreon/login');
});

Given(
  'an administrator configuring a Centreon platform and an existing user account with a first password',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
      .wait('@getLastestUserFilters')
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      });
  }
);

When(
  'the administrator enables the password reuseability and a user attempts to change its password multiple times in a row',
  () => {
    cy.reload()
      .get('div[role="tablist"] button')
      .eq(0)
      .contains('Password security policy');

    cy.get('[data-testid="local_timeBetweenPasswordChangesHours"]')
      .parent()
      .click();
    cy.get('ul li[data-value="2"]').click();
    cy.get('#Save').should('be.enabled').click();

    cy.get('@user1Id').then((idUser) => {
      cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
        cy.requestOnDatabase({
          database: 'centreon',
          query: `UPDATE contact_password SET creation_date = '${
            Number(userPasswordCreationDate) - millisecondsValueForFourHour
          }' WHERE contact_id = '${idUser}';`
        });
      });
    });

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then('user can not change password unless the minimum time has passed', () => {
  cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: true })
    .wait('@getLastestUserFilters')
    .isInProfileMenu('Edit profile')
    .should('be.visible');

  cy.visit('/centreon/main.php?p=50104&o=c').wait('@getTimeZone');
  cy.getIframeBody()
    .find('form')
    .within(() => {
      cy.get('#passwd1').should('be.visible').type('@zerty!976=Centreon');
      cy.get('#passwd2').should('be.visible').type('@zerty!976=Centreon');
    });
  cy.getIframeBody().find('#validForm input[name="submitC"]').click();

  cy.wait('@getTimeZone')
    .getIframeBody()
    .find('#Form')
    .find('#validForm input[name="change"]')
    .should('be.visible');

  cy.visit('/centreon/main.php?p=50104&o=c').wait('@getTimeZone');
  cy.getIframeBody()
    .find('#Form')
    .within(() => {
      cy.get('#passwd1').should('be.visible').type('@zerty!976=Centreon');
      cy.get('#passwd2').should('be.visible').type('@zerty!976=Centreon');
    });
  cy.getIframeBody().find('#validForm input[name="submitC"]').click();

  cy.wait('@getTimeZone')
    .getIframeBody()
    .find('#Form')
    .find('#tab1')
    .parent()
    .contains(
      "You can't change your password because the delay before changing password is not over."
    );

  cy.get('@user1Id').then((idUser) => {
    cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
      cy.requestOnDatabase({
        database: 'centreon',
        query: `UPDATE contact_password SET creation_date = '${
          Number(userPasswordCreationDate) - millisecondsValueForFourHour
        }' WHERE contact_id = '${idUser}';`
      });
    });
  });
});

Then('user can not reuse the last passwords more than 3 times', () => {
  cy.visit('/centreon/main.php?p=50104&o=c').wait('@getTimeZone');
  cy.getIframeBody()
    .find('#Form')
    .within(() => {
      cy.get('#passwd1').should('be.visible').type('@zerty!976=Centreon');
      cy.get('#passwd2').should('be.visible').type('@zerty!976=Centreon');
    });
  cy.getIframeBody().find('#validForm input[name="submitC"]').click();

  cy.wait('@getTimeZone')
    .getIframeBody()
    .find('#Form')
    .find('#tab1')
    .parent()
    .contains(
      'Your password has already been used. Please choose a different password from the previous three.'
    );

  cy.get('@user1Id').then((idUser) => {
    cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
      cy.requestOnDatabase({
        database: 'centreon',
        query: `UPDATE contact_password SET creation_date = '${Number(
          userPasswordCreationDate
        )}' WHERE contact_id = '${idUser}';`
      });
    });
  });

  cy.logout();

  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
});

Given('an existing password policy configuration and 2 non admin users', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
    .wait('@getLastestUserFilters')
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    });
});

When(
  'the administrator adds or remove a user from the excluded user list',
  () => {
    cy.get('div[role="tablist"] button')
      .eq(0)
      .contains('Password security policy');

    cy.get('div[name="excludedUsers"]').click();
    cy.get('div[role="presentation"] ul li')
      .eq(-1)
      .find('input[type="checkbox"]')
      .check();
    cy.get('#Save').should('be.enabled').click();

    cy.get('@user2Id').then((idUser) => {
      cy.get('@user2CreationPasswordDate').then((userPasswordCreationDate) => {
        cy.requestOnDatabase({
          database: 'centreon',
          query: `UPDATE contact_password SET creation_date = '${
            Number(userPasswordCreationDate) - millisecondsValueForSixMonth
          }' WHERE contact_id = '${idUser}';`
        });
      });
    });
    cy.get('@user1Id').then((idUser) => {
      cy.get('@user1CreationPasswordDate').then((userPasswordCreationDate) => {
        cy.requestOnDatabase({
          database: 'centreon',
          query: `UPDATE contact_password SET creation_date = '${
            Number(userPasswordCreationDate) - millisecondsValueForSixMonth
          }' WHERE contact_id = '${idUser}';`
        });
      });
    });

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then('the password expiration policy is applied to the removed user', () => {
  cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: false })
    .url()
    .should('include', '/reset-password');

  cy.visit('/centreon/login');
});

Then(
  'the password expiration policy is not applied anymore to the added user',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'user-non-admin-for-local-authentication',
      loginViaApi: false
    })
      .url()
      .should('include', '/monitoring/resources');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Given(
  'an administrator configuring a Centreon platform and an existing user account not blocked',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
      .wait('@getLastestUserFilters')
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      });
  }
);

When(
  'the administrator sets valid password blocking policy and the user attempts to login multiple times',
  () => {
    cy.reload()
      .get('div[role="tablist"] button')
      .eq(0)
      .contains('Password security policy');

    cy.get('#Numberofattemptsbeforeuserisblocked').type(
      '{selectall}{backspace}2'
    );
    cy.get('#Save').should('be.enabled').click();

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then('the user is locked after reaching the number of allowed attempts', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-with-wrong-password',
    loginViaApi: false
  }).reload();

  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-with-wrong-password',
    loginViaApi: false
  }).reload();

  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-with-wrong-password',
    loginViaApi: false
  }).contains('Authentication failed');

  cy.reload();
});

Then(
  'the user must wait for the defined duration before attempting again',
  () => {
    cy.get('@user2Id').then((idUser) => {
      cy.requestOnDatabase({
        database: 'centreon',
        query: `UPDATE contact SET login_attempts = NULL, blocking_time = NULL WHERE contact_id = '${idUser}';`
      });
    });

    cy.loginByTypeOfUser({
      jsonName: 'user-non-admin-for-local-authentication',
      loginViaApi: false
    })
      .url()
      .should('include', '/monitoring/resources');

    cy.logout();
  }
);

after(() => {
  cy.stopContainers();
});
