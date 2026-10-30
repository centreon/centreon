import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';

import { ConnectionMode } from '../../models';
import {
  labelByAgentTooltip,
  labelCaCertificate,
  labelCMAauthenticationToken,
  labelCreateHostAutomatically,
  labelCreateNewCMAToken,
  labelOTLPReceiver,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelSelectExistingCMATokens
} from '../../translatedLabels';
import ConnectionInitiated from './ConnectionInitiated';

interface Host {
  address: string;
  port: string;
  pollerCaCertificate: string;
  pollerCaName: string;
  token: null | { id: string; name: string };
}

interface TestValues {
  connectionMode: { id: ConnectionMode };
  configuration: {
    agentInitiated: boolean;
    pollerInitiated: boolean;
    otelPublicCertificate: string;
    otelCaCertificate: string;
    otelPrivateKey: string;
    tokens: Array<{
      name: string;
      id: number;
      inputValue?: string;
      testId?: string;
    }>;
    hosts: Array<Host>;
  };
}

const initialValues: TestValues = {
  configuration: {
    agentInitiated: false,
    hosts: [],
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    pollerInitiated: false,
    tokens: []
  },
  connectionMode: { id: ConnectionMode.secure }
};

const initialValuesWithAgentEnabled: TestValues = {
  ...initialValues,
  configuration: {
    ...initialValues.configuration,
    agentInitiated: true
  }
};

const initialValuesWithPollerEnabled: TestValues = {
  configuration: {
    agentInitiated: false,
    hosts: [
      {
        address: '',
        pollerCaCertificate: '',
        pollerCaName: '',
        port: '',
        token: null
      }
    ],
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    pollerInitiated: true,
    tokens: []
  },
  connectionMode: { id: ConnectionMode.secure }
};

const initialize = (values: TestValues = initialValues): void => {
  render(
    <BrowserRouter>
      <QueryClientProvider client={new QueryClient()}>
        <Formik initialValues={values} onSubmit={(): void => undefined}>
          <ConnectionInitiated />
        </Formik>
      </QueryClientProvider>
    </BrowserRouter>
  );
};

const getPanel = (value: 'agent' | 'poller'): HTMLElement =>
  document.querySelector(`[role="tabpanel"][id*="${value}"]`) as HTMLElement;

// The "Enable" switch of the tab shown.
const getVisibleSwitch = (): HTMLElement =>
  [...document.querySelectorAll<HTMLElement>('.MuiSwitch-input')].find(
    (element) => element.checkVisibility()
  ) as HTMLElement;

const getTab = (label: string): HTMLElement =>
  screen.getByRole('tab', { name: label });

describe('ConnectionInitiated', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('should render the component with default tab (agent)', () => {
    initialize();

    expect(screen.getByText('By agent')).toBeVisible();
    expect(screen.getByText('By poller')).toBeVisible();

    expect(getPanel('agent')).toBeVisible();
    expect(getPanel('poller')).not.toBeVisible();

    expect(document.querySelector('.MuiSwitch-root')).toBeVisible();
    expect(getVisibleSwitch()).not.toBeChecked();
    expect(screen.getByText('Enable')).toBeVisible();

    // The agent tab is selected, neither mode is marked as configured, and
    // each tab label carries an information icon.
    expect(getTab('By agent')).toHaveAttribute('aria-selected', 'true');
    expect(getTab('By poller')).toHaveAttribute('aria-selected', 'false');
    expect(
      document.querySelector('svg[data-testid="By agent selected"]')
    ).not.toBeInTheDocument();
    expect(
      document.querySelector('svg[data-testid="By poller selected"]')
    ).not.toBeInTheDocument();
    expect(
      getTab('By agent').querySelector('svg[data-testid="InfoOutlinedIcon"]')
    ).toBeVisible();
    expect(
      getTab('By poller').querySelector('svg[data-testid="InfoOutlinedIcon"]')
    ).toBeVisible();
    // The agent configuration fields only appear once enabled.
    expect(
      screen.queryByText(labelCreateHostAutomatically)
    ).not.toBeInTheDocument();
    expect(screen.queryAllByTestId(labelPublicCertificate)).toHaveLength(0);
  });

  it('should switch between tabs', async () => {
    initialize();

    await userEvent.click(screen.getByText('By poller'));
    expect(getPanel('poller')).toBeVisible();
    expect(getPanel('agent')).not.toBeVisible();

    await userEvent.click(screen.getByText('By agent'));
    expect(getPanel('agent')).toBeVisible();
    expect(getPanel('poller')).not.toBeVisible();
  });

  it('should show done icon when agent configuration is enabled', () => {
    initialize(initialValuesWithAgentEnabled);

    expect(
      screen
        .getByText('By agent')
        .parentElement?.querySelector('svg[data-testid="By agent selected"]')
    ).toBeVisible();
  });

  it('should show done icon when poller configuration is enabled', () => {
    initialize(initialValuesWithPollerEnabled);

    expect(
      screen
        .getByText('By poller')
        .parentElement?.querySelector('svg[data-testid="By poller selected"]')
    ).toBeVisible();
  });

  it('should enable agent initiated configuration', async () => {
    initialize();

    await userEvent.click(getVisibleSwitch());
    expect(getVisibleSwitch()).toBeChecked();

    expect(
      (await screen.findAllByTestId(labelPublicCertificate))[0]
    ).toBeVisible();
    expect(screen.getAllByTestId(labelCaCertificate)[0]).toBeVisible();
    expect(screen.getAllByTestId(labelPrivateKey)[0]).toBeVisible();
    expect(
      screen.getAllByTestId(labelSelectExistingCMATokens)[0]
    ).toBeVisible();

    // The agent tab is marked as configured, the poller one is not, and the
    // whole agent configuration is shown in the agent tab.
    expect(
      getTab('By agent').querySelector('svg[data-testid="By agent selected"]')
    ).toBeVisible();
    expect(
      document.querySelector('svg[data-testid="By poller selected"]')
    ).not.toBeInTheDocument();
    const agentPanel = within(getPanel('agent'));
    expect(
      agentPanel.getByRole('checkbox', { name: labelCreateHostAutomatically })
    ).not.toBeChecked();
    expect(agentPanel.getByText(labelOTLPReceiver)).toBeVisible();
    expect(agentPanel.getAllByTestId(labelPort)[0]).toBeVisible();
    expect(agentPanel.getByText(labelCMAauthenticationToken)).toBeVisible();
    expect(agentPanel.getByText(labelCreateNewCMAToken)).toBeVisible();
  });

  it('should disable agent initiated configuration', async () => {
    initialize(initialValuesWithAgentEnabled);

    await userEvent.click(getVisibleSwitch());
    expect(getVisibleSwitch()).not.toBeChecked();

    await waitFor(() => {
      expect(screen.queryAllByTestId(labelPublicCertificate)).toHaveLength(0);
    });
  });

  it('should enable poller initiated configuration', async () => {
    initialize();

    await userEvent.click(screen.getByText('By poller'));

    await userEvent.click(getVisibleSwitch());
    expect(getVisibleSwitch()).toBeChecked();

    await waitFor(() => {
      expect(getPanel('poller')).toHaveTextContent('Monitored hosts');
    });
    expect(getPanel('poller')).toHaveTextContent('Select host');
  });

  it('should disable poller initiated configuration', async () => {
    initialize(initialValuesWithPollerEnabled);

    await userEvent.click(screen.getByText('By poller'));

    await userEvent.click(getVisibleSwitch());
    expect(getVisibleSwitch()).not.toBeChecked();
  });

  it('should show tooltip icons for both tabs', async () => {
    initialize();

    const icons = document.querySelectorAll<HTMLElement>(
      'svg[data-testid="InfoOutlinedIcon"]'
    );

    expect(icons).toHaveLength(2);

    await userEvent.hover(icons[0]);

    expect(await screen.findByRole('tooltip')).toHaveTextContent(
      labelByAgentTooltip
    );
  });

  it('should not render AgentInitiated when connection mode is not secure or insecure', async () => {
    const valuesWithDifferentMode: TestValues = {
      configuration: {
        agentInitiated: true,
        hosts: [],
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        pollerInitiated: false,
        tokens: []
      },
      connectionMode: { id: ConnectionMode.noTLS }
    };

    initialize(valuesWithDifferentMode);

    // The agent configuration is shown, without the certificate fields.
    expect(
      (await screen.findAllByTestId(labelSelectExistingCMATokens))[0]
    ).toBeVisible();
    expect(screen.queryAllByTestId(labelPublicCertificate)).toHaveLength(0);
  });

  it('should render AgentInitiated when connection mode is secure', () => {
    const valuesWithSecureMode: TestValues = {
      configuration: {
        agentInitiated: true,
        hosts: [],
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        pollerInitiated: false,
        tokens: []
      },
      connectionMode: { id: ConnectionMode.secure }
    };

    initialize(valuesWithSecureMode);

    expect(screen.getAllByTestId(labelPublicCertificate)[0]).toBeVisible();
  });

  it('should maintain tab state when switching between tabs', async () => {
    initialize();

    await userEvent.click(getVisibleSwitch());

    await userEvent.click(screen.getByText('By poller'));
    expect(getPanel('poller')).toBeVisible();

    await userEvent.click(screen.getByText('By agent'));
    expect(getPanel('agent')).toBeVisible();

    expect(getVisibleSwitch()).toBeChecked();
    expect(
      (await screen.findAllByTestId(labelPublicCertificate))[0]
    ).toBeVisible();
  });
});
