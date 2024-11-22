import { renderHook } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import { useAtomValue } from 'jotai';
import { replace } from 'ramda';
import { BrowserRouter as Router } from 'react-router-dom';

import { Method, TestQueryProvider, buildListingEndpoint } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  authenticationProvidersEndpoint,
  contactsEndpoint
} from '../api/endpoints';
import { Provider } from '../models';

import {
  defaultPasswordSecurityPolicyAPI,
  defaultPasswordSecurityPolicyWithNullValues,
  retrievedPasswordSecurityPolicyAPI
} from './defaults';
import { PasswordSecurityPolicyToAPI } from './models';
import {
  labelChooseADurationBetween1HourAnd1Week,
  labelChooseADurationBetween7DaysAnd12Months,
  labelChooseAValueBetween1and10,
  labelDay,
  labelDays,
  labelDefinePasswordPasswordSecurityPolicy,
  labelDoYouWantToResetTheForm,
  labelExcludedUsers,
  labelGood,
  labelHour,
  labelLast3PasswordsCanBeReused,
  labelMinimumPasswordLength,
  labelMinimumTimeBetweenPasswordChanges,
  labelMinutes,
  labelMonth,
  labelNumberOfAttemptsBeforeUserIsBlocked,
  labelPasswordBlockingPolicy,
  labelPasswordCasePolicy,
  labelPasswordExpirationPolicy,
  labelPasswordExpiresAfter,
  labelPasswordMustContainLowerCase,
  labelPasswordMustContainNumbers,
  labelPasswordMustContainSpecialCharacters,
  labelPasswordMustContainUpperCase,
  labelReset,
  labelResetTheForm,
  labelSave,
  labelStrong,
  labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined,
  labelTimeThatMustPassBeforeNewConnection,
  labelWeak
} from './translatedLabels';

import LocalAuthentication from '.';

dayjs.extend(duration);

const LocalAuthenticationTestWithJotai = (): JSX.Element => (
  <TestQueryProvider>
    <LocalAuthentication />
  </TestQueryProvider>
);

const setComponentBeforeEach = (): void => {
  const defaultPasswordSecurityPolicyURL = authenticationProvidersEndpoint(
    Provider.Local
  );

  cy.interceptAPIRequest<PasswordSecurityPolicyToAPI>({
    alias: 'getDefaultPasswordSecurityPolicyFromAPI',
    method: Method.GET,
    path: defaultPasswordSecurityPolicyURL,
    response: defaultPasswordSecurityPolicyAPI
  });

  cy.mount({
    Component: (
      <Router>
        <LocalAuthenticationTestWithJotai />
      </Router>
    )
  });

  cy.viewport(1200, 1000);
};

before(() => {
  const userData = renderHook(() => useAtomValue(userAtom));

  userData.result.current.timezone = 'Europe/Paris';
  userData.result.current.locale = 'en_US';
});

describe('Authentication', () => {
  beforeEach(() => {
    setComponentBeforeEach();
  });

  it('updates the retrieved form recommended values and send the data when the "Save" button is clicked', () => {
    cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

    cy.findByText(labelDefinePasswordPasswordSecurityPolicy).should(
      'be.visible'
    );
    cy.findByText(labelPasswordCasePolicy).should('be.visible');
    cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
    cy.findByText(labelPasswordBlockingPolicy).should('be.visible');
    cy.findByText(labelSave).should('be.visible');

    cy.findByLabelText(labelMinimumPasswordLength).type(
      '{selectall}{backspace}45'
    );

    cy.findByTestId(labelSave).should('be.enabled').click();

    cy.interceptAPIRequest({
      alias: 'getMinLengthPasswordSecurityPolicyFromAPI',
      method: Method.GET,
      path: '**api/latest/administration/authentication/providers/local',
      response: {
        password_security_policy: {
          ...defaultPasswordSecurityPolicyAPI.password_security_policy,
          password_min_length: 45
        }
      }
    });

    cy.waitForRequest('@getMinLengthPasswordSecurityPolicyFromAPI');

    cy.makeSnapshot();
  });

  it('updates the retrieved form recommended values and reset the form to the inital values when the "Reset" button is clicked', () => {
    cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

    cy.findByText(labelDefinePasswordPasswordSecurityPolicy).should(
      'be.visible'
    );
    cy.findByText(labelPasswordCasePolicy).should('be.visible');
    cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
    cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

    cy.findByText(labelReset).should('be.disabled');

    cy.findByLabelText(labelMinimumPasswordLength).type(
      '{selectall}{backspace}45'
    );

    cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type(
      '{selectall}{backspace}8'
    );

    cy.findByText(labelReset).should('be.enabled').click();

    cy.findByText(labelResetTheForm).should('be.visible');
    cy.findByText(labelDoYouWantToResetTheForm).should('be.visible');

    cy.findAllByLabelText(labelReset).should('have.length', 2).eq(1).click();

    cy.findByLabelText(labelMinimumPasswordLength).should('have.value', 12);

    cy.makeSnapshot();
  });

  it('updates the retrieved form values and send the data when the "Save" button is clicked', () => {
    cy.interceptAPIRequest({
      alias: 'getRetrievedPasswordSecurityPolicyFromAPI',
      method: Method.GET,
      path: '**api/latest/administration/authentication/providers/local',
      response: retrievedPasswordSecurityPolicyAPI
    });

    cy.waitForRequest('@getRetrievedPasswordSecurityPolicyFromAPI');

    cy.findByText(labelDefinePasswordPasswordSecurityPolicy).should(
      'be.visible'
    );
    cy.findByText(labelPasswordCasePolicy).should('be.visible');
    cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
    cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

    cy.findByTestId(labelSave).should('be.disabled');

    cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type(
      '{selectall}{backspace}2'
    );

    cy.findByTestId(labelSave).should('be.enabled').click();

    cy.interceptAPIRequest({
      alias: 'getUpdatedAttemptsPasswordSecurityPolicyFromAPI',
      method: Method.GET,
      path: '**api/latest/administration/authentication/providers/local',
      response: {
        password_security_policy: {
          ...defaultPasswordSecurityPolicyAPI.password_security_policy,
          attempts: 2
        }
      }
    });

    cy.waitForRequest('@getUpdatedAttemptsPasswordSecurityPolicyFromAPI');

    cy.makeSnapshot();
  });

  describe('Password case policy', () => {
    beforeEach(() => {
      setComponentBeforeEach();
    });

    it('renders the password case policy fields with values', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordCasePolicy).should('be.visible');

      cy.findByLabelText(labelMinimumPasswordLength)
        .should('be.visible')
        .and('have.value', 12);

      cy.findAllByLabelText(labelPasswordMustContainLowerCase)
        .eq(0)
        .should('be.visible');
      cy.findAllByLabelText(labelPasswordMustContainUpperCase)
        .eq(0)
        .should('be.visible');
      cy.findAllByLabelText(labelPasswordMustContainNumbers)
        .eq(0)
        .should('be.visible');
      cy.findAllByLabelText(labelPasswordMustContainSpecialCharacters)
        .eq(0)
        .should('be.visible');

      cy.findAllByLabelText(labelStrong).should('be.visible');

      cy.makeSnapshot();
    });

    it('updates the password minimum length value when the corresponding input is changed', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByLabelText(labelMinimumPasswordLength)
        .should('be.visible')
        .type('{selectall}{backspace}45');

      cy.findByLabelText(labelMinimumPasswordLength).should('have.value', 45);

      cy.makeSnapshot();
    });

    it('displays the efficiency level according to the selected cases when cases button are clicked', () => {
      cy.interceptAPIRequest({
        alias: 'getDefaultPasswordSecurityPolicyWithNullValuesFromAPI',
        method: Method.GET,
        path: '**api/latest/administration/authentication/providers/local',
        response: defaultPasswordSecurityPolicyWithNullValues
      });

      cy.waitForRequest(
        '@getDefaultPasswordSecurityPolicyWithNullValuesFromAPI'
      );

      cy.findByLabelText(labelMinimumPasswordLength).should('be.visible');

      cy.findAllByLabelText(labelPasswordMustContainLowerCase).eq(0).click();
      cy.findAllByLabelText(labelPasswordMustContainUpperCase).eq(0).click();
      cy.findAllByLabelText(labelPasswordMustContainNumbers).eq(0).click();
      cy.findAllByLabelText(labelPasswordMustContainSpecialCharacters)
        .eq(0)
        .click();

      cy.findAllByText(labelStrong).should('be.visible');

      cy.findAllByLabelText(labelPasswordMustContainSpecialCharacters)
        .eq(0)
        .click();

      cy.findAllByText(labelGood).should('be.visible');

      cy.findAllByLabelText(labelPasswordMustContainNumbers).eq(0).click();

      cy.findByText(labelWeak).should('be.visible');

      cy.makeSnapshot();
    });
  });

  const retrievedContacts = {
    meta: {
      limit: 10,
      page: 1,
      search: {},
      sort_by: {},
      total: 2
    },
    result: [
      {
        alias: 'admin',
        email: 'admin@admin.com',
        id: 1,
        is_admin: true
      },
      {
        alias: 'user',
        email: 'user@admin.com',
        id: 2,
        is_admin: false
      }
    ]
  };

  describe('Password expiration policy', () => {
    beforeEach(() => {
      setComponentBeforeEach();
    });

    it('renders the password expiration policy fields with values', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
      cy.findByLabelText(labelPasswordExpiresAfter).should('be.visible');
      cy.findByLabelText(`${labelPasswordExpiresAfter} ${labelMonth}`).should(
        'be.visible'
      );
      cy.findByText(labelMonth).should('be.visible');

      cy.findByLabelText(`${labelPasswordExpiresAfter} ${labelDays}`)
        .should('be.visible')
        .and('have.text', '7');

      cy.findByText(labelDays).should('be.visible');
      cy.findByText(labelMinimumTimeBetweenPasswordChanges).should(
        'be.visible'
      );

      cy.findByLabelText(
        `${labelMinimumTimeBetweenPasswordChanges} ${labelHour}`
      )
        .should('be.visible')
        .should('have.text', '1');

      cy.findByLabelText(labelExcludedUsers).should('be.visible');

      cy.makeSnapshot();
    });

    it('does not display any error message when the password expiration time is cleared', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
      cy.findByLabelText(labelPasswordExpiresAfter).should('be.visible');

      cy.findByLabelText(`${labelPasswordExpiresAfter} ${labelDays}`).type(
        '{selectall}{backspace}'
      );

      cy.findByText(labelChooseADurationBetween7DaysAnd12Months).should(
        'not.exist'
      );

      cy.makeSnapshot();
    });

    it('does not display any error message when the delay before new password time is cleared', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
      cy.findByText(labelMinimumTimeBetweenPasswordChanges).should(
        'be.visible'
      );

      cy.findByLabelText(
        `${labelMinimumTimeBetweenPasswordChanges} ${labelHour}`
      ).type('{selectall}{backspace}');

      cy.findByText(labelChooseADurationBetween1HourAnd1Week).should(
        'not.exist'
      );

      cy.makeSnapshot();
    });

    it('selects the "Can reuse passwords" field when the corresponding switch is clicked', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');

      cy.findByLabelText(labelLast3PasswordsCanBeReused)
        .should('be.exist')
        .click();

      cy.findByLabelText(labelLast3PasswordsCanBeReused).should('be.checked');

      cy.makeSnapshot();
    });

    it('updates the excluded users field when an user is selected from the retrieved options', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');

      const contactsEndpointUrl = buildListingEndpoint({
        baseEndpoint: contactsEndpoint,
        parameters: {
          page: 1,
          search: {
            conditions: [
              {
                field: 'provider_name',
                values: {
                  $eq: 'local'
                }
              }
            ].filter(Boolean)
          },
          sort: { alias: 'ASC' }
        }
      });

      cy.interceptAPIRequest({
        alias: 'getAllContactListForExcludedUsersFromAPI',
        method: Method.GET,
        path: replace('./api/latest/configuration', '**', contactsEndpointUrl),
        response: retrievedContacts
      });

      cy.findByLabelText(labelExcludedUsers).should('be.visible').click();

      cy.waitForRequest('@getAllContactListForExcludedUsersFromAPI');

      cy.findByText('admin').should('be.visible').type('{enter}').type('{esc}');

      cy.findAllByText('admin').should('have.length', 1);

      cy.makeSnapshot();
    });
  });

  describe('Password Blocking Policy', () => {
    beforeEach(() => {
      setComponentBeforeEach();
    });

    it('renders the password blocking policy fields with values', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked)
        .should('be.visible')
        .and('have.value', '5');

      cy.findByText(labelTimeThatMustPassBeforeNewConnection).should(
        'be.visible'
      );

      cy.findByLabelText(
        `${labelTimeThatMustPassBeforeNewConnection} ${labelMinutes}`
      )
        .should('be.visible')
        .and('have.text', '15');

      cy.findByText(labelWeak).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays an error message when the number of attempts is outside the bounds', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked)
        .should('be.visible')
        .type('0');

      cy.findByText(labelChooseAValueBetween1and10).should('be.visible');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type(
        '{selectall}{backspace}8'
      );

      cy.findByText(labelChooseAValueBetween1and10).should('not.be.exist');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type('11');

      cy.findByText(labelChooseAValueBetween1and10).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays an error message in the "Time blocking duration" field when the number of attempts is cleared', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked)
        .should('be.visible')
        .type('{selectall}{backspace}');

      cy.findByText(
        labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined
      ).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the efficiency level when the number of attempts changes', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked)
        .should('be.visible')
        .type('{selectall}{backspace}2');

      cy.findAllByText(labelStrong).should('have.length', 2);

      cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type(
        '{selectall}{backspace}4'
      );

      cy.findAllByText(labelGood).should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the efficiency level when the time blocking duration changes', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByLabelText(
        `${labelTimeThatMustPassBeforeNewConnection} ${labelDay}`
      )
        .should('be.visible')
        .click();

      cy.findByText('6').click();

      cy.findAllByText(labelStrong).should('have.length', 2);

      cy.findByLabelText(
        `${labelTimeThatMustPassBeforeNewConnection} ${labelDays}`
      )
        .should('be.visible')
        .click();

      cy.findByText('3').click();

      cy.findByLabelText(
        `${labelTimeThatMustPassBeforeNewConnection} ${labelMinutes}`
      )
        .should('be.visible')
        .click();

      cy.findAllByText('0').eq(3).click();

      cy.findAllByText(labelGood).should('have.length', 2);

      cy.makeSnapshot();
    });
  });
});
