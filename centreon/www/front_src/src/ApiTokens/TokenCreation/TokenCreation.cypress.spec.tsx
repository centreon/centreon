// input token should have a copy icon , when clicking the token must be copied
// calendar

import dayjs from 'dayjs';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import relativeTime from 'dayjs/plugin/relativeTime';
import { renderHook } from '@testing-library/react';
import { useAtomValue } from 'jotai';

import { Method, useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelDuration, labelName } from '../../Resources/translatedLabels';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';
import {
  labelCancel,
  labelCreateNewToken,
  labelGenerateNewToken,
  labelOk,
  labelSecurityToken,
  labelTokenCreated,
  labelUser
} from '../translatedLabels';

import TokenCreationButton from './TokenCreationButton';
import { getDuration } from './utils';
import useCreateToken from './useCreateToken';
import { CreatedToken, UnitDate } from './models';

dayjs.extend(isSameOrBefore);
dayjs.extend(relativeTime);

const interceptRequest = ({ dataPath, parameters, alias }): void => {
  cy.fixture(dataPath).then((data) => {
    const endpoint = buildListEndpoint({
      endpoint: listConfiguredUser,
      parameters: { ...parameters }
    });
    cy.interceptAPIRequest({
      alias,
      method: Method.GET,
      path: `./api/latest${endpoint}`,
      response: data
    });
  });
};

const tokenName = 'slack';
const duration = { id: '1year', name: '1 year' };
const user = { id: 4, name: 'centreon-gorgone' };
const now = new Date(2023, 12, 18, 15, 40, 10);

const fillInputs = (): void => {
  interceptRequest({
    alias: 'getListConfiguredUsers',
    dataPath: 'apiTokens/creation/configuredUsers.json',
    parameters: { limit: 1, page: 1 }
  });
  cy.findByTestId('tokenNameInput').type(tokenName);
  cy.findByTestId(labelUser).click();
  cy.waitForRequest('@getListConfiguredUsers');
  cy.fixture('apiTokens/creation/configuredUsers.json').then(({ result }) => {
    cy.findByRole('presentation', { name: result[1].name }).click();
  });
};

const checkModalInformationWithGeneratedToken = (data: CreatedToken): void => {
  const { name, token, expirationDate, creationDate } = data;
  const durationData = getDuration({
    endTime: expirationDate,
    isCustomizeDate: false,
    startTime: creationDate
  });
  checkDataInputs({
    durationValue: durationData.name,
    name,
    token,
    userValue: data.user.name
  });
};

const checkTokenInput = (token: string): void => {
  cy.findByTestId('tokenInput').should('have.value', token);
  cy.findByTestId('tokenInput').should('have.attr', 'password');
  cy.findByTestId('token')
    .findByTestId('VisibilityOffIcon')
    .should('be.visible')
    .parent()
    .click();

  cy.makeSnapshot('token input with encrypted password');
  cy.findByTestId('tokenInput').should('have.attr', 'text');
  cy.findByTestId('token').findByTestId('VisibilityIcon').should('be.visible');
  cy.makeSnapshot('token input with displayed password');
};

const checkDataInputs = ({ durationValue, userValue, token, name }): void => {
  cy.findByTestId('tokenNameInput').should('have.value', name);
  cy.findByTestId(labelDuration).should('have.value', durationValue);
  cy.findByTestId(labelUser).should('have.value', userValue);
  cy.findByTestId('tokenInput').should('have.value', token);
};

describe('Api-token creation', () => {
  beforeEach(() => {
    cy.clock(now);
    const userData = renderHook(() => useAtomValue(userAtom));

    userData.result.current.timezone = 'Europe/Paris';

    cy.mount({
      Component: <TokenCreationButton />
    });
  });

  it('displays the token creation button', () => {
    cy.findByTestId(labelCreateNewToken).contains(labelCreateNewToken);

    cy.makeSnapshot();
  });

  it('displays the modal when clicking on token creation button', () => {
    cy.findByTestId(labelCreateNewToken).click();
    cy.findByTestId('tokenCreationDialog').contains(labelCreateNewToken);

    cy.findByTestId('tokenName')
      .findByLabelText(labelName)
      .should('be.visible');

    cy.findByTestId('tokenNameInput').should('have.attr', 'required');

    cy.findByLabelText(labelDuration)
      .findByTestId(labelDuration)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelDuration)
      .findByTestId(labelDuration)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelUser)
      .findByTestId(labelUser)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelUser)
      .findByTestId(labelUser)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByTestId(labelCancel).should('be.visible');
    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.visible')
      .should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays an updated create token button that becomes enabled when the required inputs are filled', () => {
    interceptRequest({
      alias: 'getListConfiguredUsers',
      dataPath: 'apiTokens/creation/configuredUsers.json',
      parameters: { limit: 1, page: 1 }
    });
    cy.findByTestId('tokenNameInput').type(tokenName);
    cy.findByTestId('tokenNameInput').should('have.value', tokenName);

    cy.findByTestId(labelDuration).click();
    cy.findByRole('presentation', { name: duration.name })
      .should('be.visible')
      .click();
    cy.findByTestId(labelDuration).should('have.value', duration.name);

    cy.findByTestId(labelUser).click();
    cy.waitForRequest('@getListConfiguredUsers');

    cy.fixture('apiTokens/creation/configuredUsers.json').then(({ result }) => {
      cy.findByRole('presentation', { name: result[0].name })
        .should('be.visible')
        .click();
      cy.findByTestId(labelUser).should('have.value', result[0].name);
    });

    cy.findByTestId(labelCancel).should('be.visible');

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled');

    cy.makeSnapshot();
  });

  it('displays an updated modal when generating a token with default duration ', () => {
    const { result } = renderHook(() => useCreateToken());
    const endTime = result.current?.getExpirationDate?.({
      unit: UnitDate.Year,
      value: 1
    });

    interceptRequest({
      alias: 'generateToken',
      dataPath: 'apiTokens/creation/generatedToken.json',
      parameters: {
        expiration_date: endTime,
        name: tokenName,
        user_id: user.id
      }
    });

    fillInputs();

    cy.findByTestId(labelDuration).click();
    cy.findByRole('presentation', { name: duration.name }).click();

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled')
      .click();

    cy.waitForRequest('generateToken');

    cy.contains(labelTokenCreated);
    cy.contains(labelSecurityToken);

    cy.fixture(
      'apiTokens/creation/generatedTokenWithDefaultDuration.json'
    ).then((data) => {
      checkModalInformationWithGeneratedToken(data);
      checkTokenInput(data.token);
    });

    cy.makeSnapshot();
  });

  it('displays an updated modal when generating a token with custom duration', () => {
    const { result } = renderHook(() => useLocaleDateTimeFormat());
    // endTime => now +1

    interceptRequest({
      alias: 'generateToken',
      dataPath: 'apiTokens/creation/generatedToken.json',
      parameters: {
        expiration_date: 'endTime',
        name: tokenName,
        user_id: user.id
      }
    });

    fillInputs();

    cy.findByTestId(labelDuration).click();
    cy.findByRole('presentation', { name: 'Customize' }).click();
    cy.makeSnapshot('displays the calendar when choosing a custom duration');

    cy.findByTestId(labelDuration).should('have.attr', 'disabled');
    cy.findByRole('gridcell', { selected: true }).next().click();
    cy.findByTestId(labelOk).should('be.enabled').next().click();
    // input should have value now + 1 day
  });
});
