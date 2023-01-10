import { renderHook } from '@testing-library/react-hooks/dom';
import { useAtomValue } from 'jotai';
import { BrowserRouter as Router } from 'react-router-dom';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import { replace } from 'ramda';

import { userAtom } from '@centreon/ui-context';
import { buildListingEndpoint, Method, TestQueryProvider } from '@centreon/ui';

import {
  authenticationProvidersEndpoint,
  contactsEndpoint
} from '../api/endpoints';
import { Provider } from '../models';

import {
  labelReset,
  labelDefinePasswordPasswordSecurityPolicy,
  labelDoYouWantToResetTheForm,
  labelNumberOfAttemptsBeforeUserIsBlocked,
  labelPasswordBlockingPolicy,
  labelPasswordCasePolicy,
  labelPasswordExpirationPolicy,
  labelMinimumPasswordLength,
  labelResetTheForm,
  labelSave,
  labelPasswordMustContainLowerCase,
  labelPasswordMustContainUpperCase,
  labelPasswordMustContainNumbers,
  labelPasswordMustContainSpecialCharacters,
  labelStrong,
  labelGood,
  labelWeak,
  labelPasswordExpiresAfter,
  labelMonth,
  labelDays,
  labelMinimumTimeBetweenPasswordChanges,
  labelHour,
  labelExcludedUsers,
  labelChooseADurationBetween7DaysAnd12Months,
  labelChooseADurationBetween1HourAnd1Week,
  labelLast3PasswordsCanBeReused,
  labelTimeThatMustPassBeforeNewConnection,
  labelMinutes,
  labelChooseAValueBetween1and10,
  labelThisWillNotBeUsedBecauseNumberOfAttemptsIsNotDefined,
  labelBlockingDurationMustBeLessThanOrEqualTo7Days,
  labelDay
} from './translatedLabels';
import {
  defaultPasswordSecurityPolicyAPI,
  retrievedPasswordSecurityPolicyAPI,
  defaultPasswordSecurityPolicyWithNullValues,
  securityPolicyWithInvalidDelayBeforeNewPassword,
  securityPolicyWithInvalidPasswordExpiration,
  securityPolicyWithInvalidBlockingDuration
} from './defaults';
import { PasswordSecurityPolicyToAPI } from './models';

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
    path: replace('./', '**', defaultPasswordSecurityPolicyURL),
    response: defaultPasswordSecurityPolicyAPI
  });

  cy.mount({
    Component: (
      <Router>
        <div style={{ backgroundColor: '#fff' }}>
          <LocalAuthenticationTestWithJotai />
        </div>
      </Router>
    )
  });

  cy.viewport(1200, 1000);
};

before(() => {
  document.getElementsByTagName('body')[0].style = 'margin:0px';
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

    cy.findByText(labelSave).should('be.enabled').click();

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

    cy.findByText(labelSave).should('be.disabled');

    cy.findByLabelText(labelNumberOfAttemptsBeforeUserIsBlocked).type(
      '{selectall}{backspace}2'
    );

    cy.findByText(labelSave).should('be.enabled').click();

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
    });

    it('updates the password minimum length value when the corresponding input is changed', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByLabelText(labelMinimumPasswordLength)
        .should('be.visible')
        .type('{selectall}{backspace}45');

      cy.findByLabelText(labelMinimumPasswordLength).should('have.value', 45);
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
    });

    it('displays an error message when the delay before new password time is outside the bounds', () => {
      cy.interceptAPIRequest({
        alias: 'getSecurityPolicyWithInvalidDelayBeforeNewPasswordFromAPI',
        method: Method.GET,
        path: '**api/latest/administration/authentication/providers/local',
        response: securityPolicyWithInvalidDelayBeforeNewPassword
      });
      cy.waitForRequest(
        '@getSecurityPolicyWithInvalidDelayBeforeNewPasswordFromAPI'
      );

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');
      cy.findByText(labelMinimumTimeBetweenPasswordChanges).should(
        'be.visible'
      );
      cy.findByText(labelChooseADurationBetween1HourAnd1Week).should(
        'be.visible'
      );

      cy.interceptAPIRequest({
        alias: 'getSecurityPolicyWithInvalidPasswordExpirationFromAPI',
        method: Method.GET,
        path: '**api/latest/administration/authentication/providers/local',
        response: securityPolicyWithInvalidPasswordExpiration
      });

      cy.mount({
        Component: (
          <Router>
            <div style={{ backgroundColor: '#fff' }}>
              <LocalAuthenticationTestWithJotai />
            </div>
          </Router>
        )
      });
      cy.waitForRequest(
        '@getSecurityPolicyWithInvalidPasswordExpirationFromAPI'
      );

      cy.findByText(labelChooseADurationBetween7DaysAnd12Months).should(
        'be.visible'
      );
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
    });

    it('selects the "Can reuse passwords" field when the corresponding switch is clicked', () => {
      cy.waitForRequest('@getDefaultPasswordSecurityPolicyFromAPI');

      cy.findByText(labelPasswordExpirationPolicy).should('be.visible');

      cy.findByLabelText(labelLast3PasswordsCanBeReused)
        .should('be.exist')
        .click();

      cy.findByLabelText(labelLast3PasswordsCanBeReused).should('be.checked');
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
    });

    it('displays an error message when the time blocking duration is 7 days and 1 hour', () => {
      cy.interceptAPIRequest({
        alias: 'getSecurityPolicyWithInvalidBlockingDurationFromAPI',
        method: Method.GET,
        path: '**api/latest/administration/authentication/providers/local',
        response: securityPolicyWithInvalidBlockingDuration
      });

      cy.waitForRequest('@getSecurityPolicyWithInvalidBlockingDurationFromAPI');

      cy.findByText(labelPasswordBlockingPolicy).should('be.visible');

      cy.findByText(labelBlockingDurationMustBeLessThanOrEqualTo7Days).should(
        'be.visible'
      );
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
    });
  });
});
