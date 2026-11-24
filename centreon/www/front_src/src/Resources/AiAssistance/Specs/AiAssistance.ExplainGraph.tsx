import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { aiGraphExplanationEndpoint } from '../api/endpoints';
import ExplainGraph from '../ExplainGraph/ExplainGraph';
import {
  labelAiBudgetExhausted,
  labelAnswerTruncated,
  labelExplainGraph,
  labelUnreadableAnswer
} from '../translatedLabels';
import { maxGraphMetrics, maxTextLength } from '../utils';
import {
  closeDialog,
  expectTooltip,
  interceptSlowFirstRequest,
  interceptUnreadableAnswer,
  waitForAllAnswers
} from './helpers';
import initialize from './initialize';
import {
  aiErrorMessage,
  getErrorResponse,
  graphDetails,
  graphExplanationResponse,
  graphMetrics,
  model
} from './utils';

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

    it('is visible but disabled when the AI budget is exhausted', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />,
        { availability: { is_available: false, reason: 'budget_exhausted' } }
      );

      await waitForRequest('@getAiAvailability');

      const button = await screen.findByTestId(labelExplainGraph);

      expect(button).toBeDisabled();
      await expectTooltip(button, labelAiBudgetExhausted);
    });

    it('truncates the performance data to the length the API accepts', async () => {
      initialize(
        <ExplainGraph
          details={{
            ...graphDetails,
            performance_data: 'x'.repeat(maxTextLength + 1)
          }}
          metrics={graphMetrics}
        />
      );

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      const { request } = await waitForRequest('@explainGraph');

      expect(
        (request.body as { performance_data: string }).performance_data
      ).toHaveLength(maxTextLength);
    });

    it('sends the first metrics only when the graph has more than the API accepts', async () => {
      const manyMetrics = Array.from(
        { length: maxGraphMetrics + 1 },
        (_, index) => ({ ...graphMetrics[0], name: `metric-${index}` })
      );

      initialize(<ExplainGraph details={graphDetails} metrics={manyMetrics} />);

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      const { request } = await waitForRequest('@explainGraph');
      const { metrics } = request.body as GraphPayload;

      expect(metrics).toHaveLength(maxGraphMetrics);
      expect(metrics[0].name).toBe('metric-0');
      expect(metrics.at(-1)?.name).toBe(`metric-${maxGraphMetrics - 1}`);
    });

    it('says when the answer was cut short', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />,
        {
          responses: {
            explainGraph: { ...graphExplanationResponse, truncated: true }
          }
        }
      );

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      expect(await screen.findByText(labelAnswerTruncated)).toBeVisible();
    });

    it('closes the dialog and shows an error when the answer cannot be read', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />
      );
      interceptUnreadableAnswer({
        alias: 'unreadableGraphExplanation',
        path: aiGraphExplanationEndpoint
      });

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      await waitForRequest('@unreadableGraphExplanation');

      expect(await screen.findByText(labelUnreadableAnswer)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.getByTestId(labelExplainGraph)).toBeEnabled();
    });

    it('closes the dialog and shows the error once when the explanation fails', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />,
        { failures: { explainGraph: 502 } }
      );

      await userEvent.click(await screen.findByTestId(labelExplainGraph));

      await waitForRequest('@explainGraph');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
    });

    it('keeps the dialog of a newer explanation open when a superseded one fails', async () => {
      initialize(
        <ExplainGraph details={graphDetails} metrics={graphMetrics} />
      );

      const releaseFirst = interceptSlowFirstRequest({
        alias: 'slowGraphExplanation',
        first: { body: getErrorResponse(502), status: 502 },
        next: { explanation: 'Second explanation', model },
        path: aiGraphExplanationEndpoint
      });

      await userEvent.click(await screen.findByTestId(labelExplainGraph));
      await closeDialog();

      await userEvent.click(screen.getByTestId(labelExplainGraph));
      expect(await screen.findByText('Second explanation')).toBeVisible();

      releaseFirst();
      await waitForAllAnswers('@slowGraphExplanation');

      expect(screen.getByRole('dialog')).toBeVisible();
      expect(screen.getByText('Second explanation')).toBeVisible();
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
