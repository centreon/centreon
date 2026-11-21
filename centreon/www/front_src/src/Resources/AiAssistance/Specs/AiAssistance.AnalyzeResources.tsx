import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import AnalyzeResources from '../AnalyzeResources/AnalyzeResources';
import { labelAnalyze, labelExplain } from '../translatedLabels';
import initialize from './initialize';
import { model, selectedResources } from './utils';

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
