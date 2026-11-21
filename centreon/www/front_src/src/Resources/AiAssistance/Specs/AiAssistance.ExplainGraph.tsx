import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import ExplainGraph from '../ExplainGraph/ExplainGraph';
import { labelExplainGraph } from '../translatedLabels';
import initialize from './initialize';
import { graphDetails, graphMetrics, model } from './utils';

interface GraphPayload {
  metrics: Array<{ current: number | null; name: string }>;
  name: string;
  parent_name: string | null;
  resource_type: string;
  time_range: string | null;
}

export default (): void => {
  describe('ExplainGraph', () => {
    it('explains the displayed graph in a dialog', async () => {
      initialize(
        <ExplainGraph
          details={graphDetails}
          metrics={graphMetrics}
          timeRange="the last 24 hours"
        />
      );

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      const { request } = await waitForRequest('@explainGraph');
      const body = request.body as GraphPayload;

      expect(body.name).toBe('Ping');
      expect(body.resource_type).toBe('service');
      expect(body.parent_name).toBe('db-server-01');
      expect(body.time_range).toBe('the last 24 hours');
      expect(body.metrics).toHaveLength(1);
      expect(body.metrics[0].name).toBe('Round-Trip-Time Average (ms)');
      expect(body.metrics[0].current).toBe(5);

      expect(
        await screen.findByText('Round-trip time is stable around 5ms', {
          exact: false
        })
      ).toBeVisible();
      expect(await screen.findByText(model, { exact: false })).toBeVisible();
      expect(
        await screen.findByText('on db-server-01', { exact: false })
      ).toBeVisible();
    });

    it('disables the action when there are no metrics', async () => {
      initialize(<ExplainGraph details={graphDetails} metrics={[]} />);

      await waitForRequest('@getAiAvailability');

      expect(await screen.findByTestId(labelExplainGraph)).toBeDisabled();
    });

    it('is hidden when no AI provider is enabled', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />,
        {
          availability: { is_available: false, reason: 'no_provider' }
        }
      );

      await waitForRequest('@getAiAvailability');

      await waitFor(() =>
        expect(screen.queryByTestId(labelExplainGraph)).not.toBeInTheDocument()
      );
    });
  });
};
