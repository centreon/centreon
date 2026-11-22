import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { Resource } from '../../models';
import { aiResultLoadingTestId } from '../AiResultDialog';
import AnalyzeResources from '../AnalyzeResources/AnalyzeResources';
import { aiAlertCorrelationEndpoint } from '../api/endpoints';
import {
  labelAiBudgetExhausted,
  labelAnalyze,
  labelAnalyzeMostSevere,
  labelExplain
} from '../translatedLabels';
import { maxCorrelatedResources, maxTextLength } from '../utils';
import {
  closeDialog,
  expectTooltip,
  interceptSlowFirstRequest,
  waitForAllAnswers
} from './helpers';
import initialize from './initialize';
import { aiErrorMessage, model, selectedResources } from './utils';

// More resources than the API accepts: the OK ones first, a single critical
// one last.
const tooManyResources = [
  ...Array.from({ length: maxCorrelatedResources }, (_, index) => ({
    name: `ok-${index}`,
    status: { name: 'OK', severity_code: 5 },
    type: 'service'
  })),
  {
    name: 'critical-service',
    status: { name: 'CRITICAL', severity_code: 1 },
    type: 'service'
  }
] as unknown as Array<Resource>;

interface ResourcePayload {
  parent_name: string | null;
  resource_type: string;
  status: string;
}

export default (): void => {
  describe('AnalyzeResources', () => {
    it('analyzes the selected resources together', async () => {
      initialize(<AnalyzeResources resources={selectedResources} />);

      await userEvent.click(await screen.findByTestId(labelAnalyze));

      const { request } = await waitForRequest('@correlateAlerts');
      const { resources } = request.body as {
        resources: Array<ResourcePayload>;
      };

      expect(resources).toHaveLength(2);
      expect(resources[0].resource_type).toBe('service');
      expect(resources[0].parent_name).toBe('db-server-01');
      expect(resources[1].status).toBe('DOWN');

      expect(
        await screen.findByText('The host db-server-01 is down', {
          exact: false
        })
      ).toBeVisible();
      expect(await screen.findByText(model, { exact: false })).toBeVisible();
    });

    it('explains a single selected resource through the explanation feature', async () => {
      initialize(<AnalyzeResources resources={[selectedResources[0]]} />);

      // a single resource turns the action into the explain feature
      await userEvent.click(await screen.findByTestId(labelExplain));

      const { request } = await waitForRequest('@explainResource');
      const body = request.body as Record<string, unknown>;

      expect(body.name).toBe('Ping');
      expect(body.resource_type).toBe('service');
      expect(body.status).toBe('CRITICAL');
      expect(body.information).toBe('CRITICAL - Packet loss = 100%');
      expect(body.parent_name).toBe('db-server-01');

      expect(
        await screen.findByText('The ping check lost all packets', {
          exact: false
        })
      ).toBeVisible();
      expect(await screen.findByText(model, { exact: false })).toBeVisible();
      // a single resource reuses the explanation dialog, host in the title
      expect(
        await screen.findByText('on db-server-01', { exact: false })
      ).toBeVisible();
    });

    it('disables the action when no resource is selected', async () => {
      initialize(<AnalyzeResources resources={[]} />);

      await waitForRequest('@getAiAvailability');

      expect(await screen.findByTestId(labelAnalyze)).toBeDisabled();
    });

    it('is visible but disabled when the AI budget is exhausted', async () => {
      initialize(<AnalyzeResources resources={selectedResources} />, {
        availability: { is_available: false, reason: 'budget_exhausted' }
      });

      await waitForRequest('@getAiAvailability');

      const button = await screen.findByTestId(labelAnalyze);

      expect(button).toBeVisible();
      expect(button).toBeDisabled();
      await expectTooltip(button, labelAiBudgetExhausted);
    });

    it('sends the most severe resources only when more are selected than the API accepts', async () => {
      initialize(<AnalyzeResources resources={tooManyResources} />);

      const button = await screen.findByTestId(labelAnalyze);

      await expectTooltip(
        button,
        labelAnalyzeMostSevere.replace(
          '{{max}}',
          String(maxCorrelatedResources)
        )
      );

      await userEvent.click(button);

      const { request } = await waitForRequest('@correlateAlerts');
      const { resources } = request.body as {
        resources: Array<{ name: string }>;
      };

      expect(resources).toHaveLength(maxCorrelatedResources);
      expect(resources[0].name).toBe('critical-service');
    });

    it('truncates the check output to the length the API accepts', async () => {
      const longOutput = 'x'.repeat(maxTextLength + 10);

      initialize(
        <AnalyzeResources
          resources={selectedResources.map((resource) => ({
            ...resource,
            information: longOutput
          }))}
        />
      );

      await userEvent.click(await screen.findByTestId(labelAnalyze));

      const { request } = await waitForRequest('@correlateAlerts');
      const { resources } = request.body as {
        resources: Array<{ information: string }>;
      };

      expect(resources[0].information).toHaveLength(maxTextLength);
    });

    it('closes the dialog and shows the error once when the analysis fails', async () => {
      initialize(<AnalyzeResources resources={selectedResources} />, {
        failures: { correlateAlerts: 502 }
      });

      await userEvent.click(await screen.findByTestId(labelAnalyze));

      await waitForRequest('@correlateAlerts');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.queryByTestId(aiResultLoadingTestId)).toBeNull();
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
    });

    it('closes the dialog and shows the error once when the explanation fails', async () => {
      initialize(<AnalyzeResources resources={[selectedResources[0]]} />, {
        failures: { explainResource: 409 }
      });

      await userEvent.click(await screen.findByTestId(labelExplain));

      await waitForRequest('@explainResource');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
    });

    it('ignores the late answer of a superseded analysis', async () => {
      initialize(<AnalyzeResources resources={selectedResources} />);

      const releaseFirst = interceptSlowFirstRequest({
        alias: 'slowCorrelation',
        first: { body: { analysis: 'First analysis', model } },
        next: { analysis: 'Second analysis', model },
        path: aiAlertCorrelationEndpoint
      });

      await userEvent.click(await screen.findByTestId(labelAnalyze));
      expect(await screen.findByTestId(aiResultLoadingTestId)).toBeVisible();
      await closeDialog();

      await userEvent.click(screen.getByTestId(labelAnalyze));
      expect(await screen.findByText('Second analysis')).toBeVisible();

      releaseFirst();
      await waitForAllAnswers('@slowCorrelation');

      expect(screen.getByText('Second analysis')).toBeVisible();
      expect(screen.queryByText('First analysis')).toBeNull();
    });

    it('is hidden when no AI provider is enabled', async () => {
      initialize(<AnalyzeResources resources={selectedResources} />, {
        availability: { is_available: false, reason: 'no_provider' }
      });

      await waitForRequest('@getAiAvailability');

      await waitFor(() =>
        expect(screen.queryByTestId(labelAnalyze)).not.toBeInTheDocument()
      );
    });
  });
};
