import userEvent from '@testing-library/user-event';
import fetchMock from 'jest-fetch-mock';

import { TestQueryProvider } from '@centreon/ui';
import {
  getFetchCall,
  mockResponseOnce,
  render,
  RenderResult,
  resetMocks,
  screen,
  waitFor
} from '@centreon/ui/src/testRenderer';

import { Provider } from '../models';
import { authenticationProvidersEndpoint } from '../api/endpoints';
import {
  labelDoYouWantToResetTheForm,
  labelReset,
  labelResetTheForm,
  labelSave
} from '../Local/translatedLabels';
import { labelActivation } from '../translatedLabels';
import { labelMixed } from '../shared/translatedLabels';

import {
  labelBlacklistClientAddresses,
  labelDefineWebSSOConfiguration,
  labelEnableWebSSOAuthentication,
  labelInvalidIPAddress,
  labelInvalidRegex,
  labelLoginHeaderAttributeName,
  labelPatternMatchLogin,
  labelPatternReplaceLogin,
  labelTrustedClientAddresses,
  labelWebSSOOnly
} from './translatedLabels';
import { retrievedWebSSOConfiguration } from './defaults';

import WebSSOConfigurationForm from '.';

jest.mock('../logos/providerPadlock.svg');

const renderWebSSOConfigurationForm = (): RenderResult =>
  render(
    <TestQueryProvider>
      <WebSSOConfigurationForm />
    </TestQueryProvider>
  );

describe('Web SSO configuration form', () => {
  beforeEach(() => {
    resetMocks();
    mockResponseOnce({
      data: retrievedWebSSOConfiguration
    });
  });

  it('saves the web SSO configuration when a field is modified and the "Save" button is clicked', async () => {
    renderWebSSOConfigurationForm();

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        authenticationProvidersEndpoint(Provider.WebSSO)
      );
    });

    await waitFor(() => {
      expect(screen.getByText(labelActivation)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(
        screen.getByLabelText(labelEnableWebSSOAuthentication)
      ).toBeChecked();
    });

    userEvent.type(
      screen.getByLabelText(labelLoginHeaderAttributeName),
      'admin'
    );
    userEvent.tab();

    await waitFor(() => {
      expect(screen.getByLabelText('save button')).not.toBeDisabled();
    });

    userEvent.click(screen.getByText(labelSave));

    await waitFor(() => {
      expect(getFetchCall(1)).toEqual(
        authenticationProvidersEndpoint(Provider.WebSSO)
      );

      expect(fetchMock.mock.calls[1][1]?.body).toEqual(
        JSON.stringify({
          ...retrievedWebSSOConfiguration,
          login_header_attribute: 'admin'
        })
      );
    });
  });

  it('displays the form', async () => {
    renderWebSSOConfigurationForm();

    expect(
      screen.getByText(labelDefineWebSSOConfiguration)
    ).toBeInTheDocument();

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        authenticationProvidersEndpoint(Provider.WebSSO)
      );
    });

    await waitFor(() => {
      expect(screen.getByText(labelActivation)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(
        screen.getByLabelText(labelEnableWebSSOAuthentication)
      ).toBeChecked();
    });

    expect(screen.getByLabelText(labelWebSSOOnly)).not.toBeChecked();
    expect(screen.getByLabelText(labelMixed)).toBeChecked();
    expect(
      screen.getByLabelText(`${labelTrustedClientAddresses}`)
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText(`${labelBlacklistClientAddresses}`)
    ).toBeInTheDocument();
    expect(screen.getAllByText('127.0.0.1')).toHaveLength(2);
    expect(screen.getByLabelText(labelLoginHeaderAttributeName)).toHaveValue(
      ''
    );
    expect(screen.getByLabelText(labelPatternMatchLogin)).toHaveValue('');
    expect(screen.getByLabelText(labelPatternReplaceLogin)).toHaveValue('');
  });

  it('displays an error message when fields are not correctly formatted', async () => {
    renderWebSSOConfigurationForm();

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        authenticationProvidersEndpoint(Provider.WebSSO)
      );
    });

    await waitFor(() => {
      expect(screen.getByText(labelActivation)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(
        screen.getByLabelText(labelEnableWebSSOAuthentication)
      ).toBeChecked();
    });

    userEvent.type(
      screen.getByLabelText(labelPatternMatchLogin),
      '{selectall}{backspace}invalid-pattern^'
    );
    userEvent.tab();

    await waitFor(() => {
      expect(screen.getByText(labelInvalidRegex)).toBeInTheDocument();
    });

    userEvent.type(
      screen.getByLabelText(labelPatternReplaceLogin),
      '{selectall}{backspace}$invalid-pattern'
    );
    userEvent.tab();

    await waitFor(() => {
      expect(screen.getAllByText(labelInvalidRegex)).toHaveLength(2);
    });

    userEvent.type(
      screen.getByLabelText(`${labelTrustedClientAddresses}`),
      'invalid domain'
    );
    userEvent.keyboard('{Enter}');

    await waitFor(() => {
      expect(
        screen.getByText(`invalid domain: ${labelInvalidIPAddress}`)
      ).toBeInTheDocument();
    });

    userEvent.type(
      screen.getByLabelText(`${labelBlacklistClientAddresses}`),
      '127.0.0.1111'
    );
    userEvent.keyboard('{Enter}');

    await waitFor(() => {
      expect(
        screen.getByText(`127.0.0.1111: ${labelInvalidIPAddress}`)
      ).toBeInTheDocument();
    });

    expect(screen.getByText(labelSave)).toBeDisabled();
    expect(screen.getByText(labelReset)).not.toBeDisabled();
  });

  it('resets the web SSO configuration when a field is modified and the "Reset" button is clicked', async () => {
    renderWebSSOConfigurationForm();

    await waitFor(() => {
      expect(getFetchCall(0)).toEqual(
        authenticationProvidersEndpoint(Provider.WebSSO)
      );
    });

    await waitFor(() => {
      expect(screen.getByText(labelActivation)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(
        screen.getByLabelText(labelEnableWebSSOAuthentication)
      ).toBeChecked();
    });

    userEvent.type(
      screen.getByLabelText(labelLoginHeaderAttributeName),
      'admin'
    );
    userEvent.tab();

    await waitFor(() => {
      expect(screen.getByText(labelReset)).not.toBeDisabled();
    });

    userEvent.click(screen.getByText(labelReset));

    await waitFor(() => {
      expect(screen.getByText(labelResetTheForm)).toBeInTheDocument();
    });

    expect(screen.getByText(labelDoYouWantToResetTheForm)).toBeInTheDocument();

    userEvent.click(screen.getAllByText(labelReset)[1]);

    await waitFor(() => {
      expect(screen.getByLabelText(labelLoginHeaderAttributeName)).toHaveValue(
        ''
      );
    });
  });
});
