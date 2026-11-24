import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';

import { toolbarWidths } from '../../Actions/model';
import useMediaQueryListing from '../../Actions/Resource/useMediaQueryListing';
import initialize from './initialize';

const toolbarTestId = 'toolbar-widths';

// What the resources toolbar reads to decide whether to condense.
const ToolbarWidthsProbe = (): JSX.Element => {
  const { applyBreakPoint, widths } = useMediaQueryListing();

  return (
    <div data-testid={toolbarTestId}>
      {JSON.stringify({ applyBreakPoint, condensed: widths.condensed })}
    </div>
  );
};

const getToolbar = (): { applyBreakPoint: boolean; condensed: number } =>
  JSON.parse(screen.getByTestId(toolbarTestId).textContent ?? '{}');

// Wider than the default breakpoint, narrower than the one with AI.
const windowWidth = 1300;

export default (): void => {
  describe('Toolbar widths', () => {
    const originalWidth = Object.getOwnPropertyDescriptor(window, 'innerWidth');

    beforeEach(() => {
      Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        value: windowWidth
      });
    });

    afterEach(() => {
      if (originalWidth) {
        Object.defineProperty(window, 'innerWidth', originalWidth);

        return;
      }

      Reflect.deleteProperty(window, 'innerWidth');
    });

    it('condenses the toolbar sooner when an AI provider is enabled', async () => {
      initialize(<ToolbarWidthsProbe />);

      await waitForRequest('@getAiAvailability');

      await waitFor(() =>
        expect(getToolbar()).toEqual({
          applyBreakPoint: true,
          condensed: toolbarWidths.withAi.condensed
        })
      );
    });

    it('condenses the toolbar sooner when the AI budget is exhausted', async () => {
      initialize(<ToolbarWidthsProbe />, {
        availability: { is_available: false, reason: 'budget_exhausted' }
      });

      await waitForRequest('@getAiAvailability');

      await waitFor(() =>
        expect(getToolbar()).toEqual({
          applyBreakPoint: true,
          condensed: toolbarWidths.withAi.condensed
        })
      );
    });

    it('keeps the full toolbar when no AI provider is enabled', async () => {
      initialize(<ToolbarWidthsProbe />, {
        availability: { is_available: false, reason: 'no_provider' }
      });

      await waitForRequest('@getAiAvailability');
      // the answer is applied
      await new Promise((resolve) => {
        setTimeout(resolve, 100);
      });

      expect(getToolbar()).toEqual({
        applyBreakPoint: false,
        condensed: toolbarWidths.default.condensed
      });
    });
  });
};
