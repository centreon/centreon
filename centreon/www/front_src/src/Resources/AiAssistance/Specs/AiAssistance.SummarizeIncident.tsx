import { rs } from '@rstest/core';
import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import SummarizeIncident from '../SummarizeIncident/SummarizeIncident';
import {
  labelAiBudgetExhausted,
  labelCopy,
  labelNoRecentEvents,
  labelSummarize
} from '../translatedLabels';
import { expectTooltip } from './helpers';
import initialize from './initialize';
import { aiErrorMessage, incidentDetails, model, summary } from './utils';

const dayInMs = 24 * 60 * 60 * 1000;

// The start of the window the request asks the events since.
const getWindowStart = (url: URL): number => {
  const [, since] =
    (url.searchParams.get('search') ?? '').match(/"\$gt":"([^"]+)"/) ?? [];

  return new Date(since).getTime();
};

interface SummaryPayload {
  events: Array<{ author: string | null; status: string | null; type: string }>;
  is_truncated: boolean;
  name: string;
  status: string;
}

export default (): void => {
  describe('SummarizeIncident', () => {
    it('displays the summary built from the recent timeline events', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />);

      await userEvent.click(await screen.findByTestId(labelSummarize));

      const { request: timelineRequest } =
        await waitForRequest('@listTimeline');

      expect(timelineRequest.url.search).toContain('limit=100');
      // the events of the last 24 hours
      expect(
        Math.abs(Date.now() - dayInMs - getWindowStart(timelineRequest.url))
      ).toBeLessThan(60_000);

      const { request } = await waitForRequest('@summarizeIncident');
      const body = request.body as SummaryPayload;

      expect(body.name).toBe('Ping');
      expect(body.status).toBe('CRITICAL');
      expect(body.is_truncated).toBe(false);
      // events are sent oldest first
      expect(body.events[0].type).toBe('event');
      expect(body.events[0].status).toBe('CRITICAL');
      expect(body.events[1].type).toBe('acknowledgement');
      expect(body.events[1].author).toBe('admin');

      expect(
        await screen.findByText('Ping lost all packets and was acknowledged', {
          exact: false
        })
      ).toBeVisible();
      expect(await screen.findByText(model, { exact: false })).toBeVisible();
      // the title carries the host so a service is unambiguous
      expect(
        await screen.findByText('on db-server-01', { exact: false })
      ).toBeVisible();
    });

    it('copies the summary to the clipboard', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />);

      const writeText = rs
        .spyOn(navigator.clipboard, 'writeText')
        .mockResolvedValue(undefined);

      await userEvent.click(await screen.findByTestId(labelSummarize));
      await waitForRequest('@summarizeIncident');

      const copyButton = await screen.findByTestId(labelCopy);
      await waitFor(() => expect(copyButton).toBeEnabled());
      await userEvent.click(copyButton);

      await waitFor(() => expect(writeText).toHaveBeenCalledWith(summary));
    });

    it('flags the summary as truncated when more events exist in the window', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />, {
        timelineTotal: 150
      });

      await userEvent.click(await screen.findByTestId(labelSummarize));

      const { request } = await waitForRequest('@summarizeIncident');

      expect((request.body as SummaryPayload).is_truncated).toBe(true);
    });

    it('disables the action when the resource has no event in the last 24 hours', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />, {
        recentTimelineTotal: 0
      });

      // the summarized window, whatever period the Timeline tab displays
      const { request } = await waitForRequest('@countRecentTimelineEvents');

      expect(
        Math.abs(Date.now() - dayInMs - getWindowStart(request.url))
      ).toBeLessThan(60_000);

      const button = await screen.findByTestId(labelSummarize);

      await waitFor(() => expect(button).toBeDisabled());
      await expectTooltip(button, labelNoRecentEvents);
    });

    it('enables the action when the resource has events in the last 24 hours', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />, {
        recentTimelineTotal: 1
      });

      await waitForRequest('@countRecentTimelineEvents');

      expect(await screen.findByTestId(labelSummarize)).toBeEnabled();
    });

    it('is visible but disabled when the AI budget is exhausted', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />, {
        availability: { is_available: false, reason: 'budget_exhausted' }
      });

      await waitForRequest('@getAiAvailability');

      const button = await screen.findByTestId(labelSummarize);

      expect(button).toBeDisabled();
      await expectTooltip(button, labelAiBudgetExhausted);
    });

    it('closes the dialog and shows the error once when the summary fails', async () => {
      initialize(<SummarizeIncident details={incidentDetails} />, {
        failures: { summarizeIncident: 409 }
      });

      await userEvent.click(await screen.findByTestId(labelSummarize));

      await waitForRequest('@summarizeIncident');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
    });
  });
};
