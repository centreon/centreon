import { userAtom } from '@centreon/ui-context';

import { render } from '@test-setup/render';
import { renderHook, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals, update } from 'ramda';

import { labelAvg, labelMax, labelMin } from '../Chart/translatedLabels';
import type { LineChartData } from '../common/models';
import { getNormalizedChart } from '../common/testUtils';
import dataMissingPoint from '../mockedData/dataWithMissingPoint.json';
import dataLastWeek from '../mockedData/lastWeek.json';
import dataPingService from '../mockedData/pingService.json';
import dataPingServiceMixedStacked from '../mockedData/pingServiceMixedStacked.json';
import dataPingServiceStacked from '../mockedData/pingServiceStacked.json';
import dataPingServiceLinesStackKeys from '../mockedData/pingServiceWithStackedKeys.json';
import BarChart, { type BarChartProps } from './BarChart';

const defaultStart = new Date(
  dayjs(Date.now()).subtract(24, 'hour').toDate().getTime()
).toISOString();

const defaultEnd = new Date(Date.now()).toISOString();

const hoveredStackedBarIndex = 6;

const dataPingServiceStackedWithUndefinedValues = {
  ...dataPingServiceStacked,
  metrics: dataPingServiceStacked.metrics.map((metric) => {
    if (equals(metric.metric_id, 3)) {
      return {
        ...metric,
        data: update(hoveredStackedBarIndex, null, metric.data)
      };
    }

    if (equals(metric.metric_id, 4)) {
      return {
        ...metric,
        data: update(hoveredStackedBarIndex, 0, metric.data)
      };
    }

    return metric;
  })
};

const defaultArgs = {
  end: defaultEnd,
  height: 500,
  loading: false,
  start: defaultStart
};

const initialize = ({
  data = dataPingService,
  legend,
  tooltip,
  axis,
  orientation,
  barStyle,
  min,
  max,
  timeAreas
}: Partial<
  Pick<
    BarChartProps,
    | 'legend'
    | 'axis'
    | 'barStyle'
    | 'orientation'
    | 'tooltip'
    | 'min'
    | 'max'
    | 'timeAreas'
  >
> & { data?: unknown }): void => {
  // The Cypress spec ran in a 1280×800 (macbook-13) viewport.
  render(
    <div style={{ height: 800, width: '100%' }}>
      <BarChart
        axis={axis}
        barStyle={barStyle}
        data={data as LineChartData}
        legend={legend}
        max={max}
        min={min}
        orientation={orientation ?? 'horizontal'}
        timeAreas={timeAreas}
        tooltip={tooltip}
        {...defaultArgs}
      />
    </div>
  );
};

const checkWidth = async (): Promise<void> => {
  await waitFor(() =>
    expect(
      document.querySelector('g[class*="visx-rows"] > line')
    ).toHaveAttribute('x2')
  );
};

// The axes render twice (the second pass measures the tick labels): query again
// until the label is the one on the page.
const expectVisibleText = async (text: RegExp): Promise<void> => {
  await waitFor(() => expect(screen.getAllByText(text)[0]).toBeVisible());
};

const checkAxes = async (): Promise<void> => {
  await expectVisibleText(/0 ms/);
  await expectVisibleText(/20/);
  await expectVisibleText(/:40 AM/);
};

const getBar = (testIdPrefix: string): Element =>
  document.querySelector(`[data-testid^="${testIdPrefix}"]`) as Element;

const expectSameStack = ({
  testIdPrefixes,
  orientation
}: {
  testIdPrefixes: Array<string>;
  orientation: string;
}): void => {
  const side = equals(orientation, 'horizontal') ? 'left' : 'top';
  const [firstBar, ...otherBars] = testIdPrefixes.map(
    (prefix) => getBar(prefix).getBoundingClientRect()[side]
  );

  for (const bar of otherBars) {
    expect(bar).toBeCloseTo(firstBar, 0);
  }
};

const expectBarDirection = ({
  testId,
  orientation
}: {
  testId: string;
  orientation: string;
}): void => {
  const { width, height } = screen.getByTestId(testId).getBoundingClientRect();

  if (equals(orientation, 'horizontal')) {
    expect(height).toBeGreaterThan(width);

    return;
  }

  expect(width).toBeGreaterThan(height);
};

const getTooltip = async (): Promise<HTMLElement> => {
  const tooltip = await screen.findByRole('tooltip');
  await waitFor(() => expect(tooltip).toBeVisible());

  return tooltip;
};

beforeEach(() => {
  expect.hasAssertions();
});

describe('Bar chart', () => {
  it('displays a tooltip when a single bar is hovered', async () => {
    initialize({
      orientation: 'horizontal'
    });

    await checkWidth();
    await checkAxes();

    await userEvent.hover(
      await screen.findByTestId('stacked-bar-10-0-7650.368581547736')
    );

    const tooltip = await getTooltip();
    expect(within(tooltip).getByText(/06\/19\/2024/)).toBeVisible();
    await expectVisibleText(/Centreon-Server: Round-Trip Maximum Time/);
    expect(within(tooltip).getByText(/7.47 KiB/)).toBeVisible();
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays a tooltip when a stacked bar is hovered', async () => {
    initialize({
      data: dataPingServiceStacked,
      orientation: 'horizontal',
      tooltip: {
        mode: 'all',
        sortOrder: 'ascending'
      }
    });

    await checkWidth();
    await checkAxes();

    await userEvent.hover(await screen.findByTestId('stacked-bar-1-0-0.05296'));

    const tooltip = await getTooltip();
    expect(within(tooltip).getByText(/06\/19\/2024/)).toBeVisible();
    expect(
      within(tooltip).getByText(/Centreon-Server: Round-Trip Maximum Time/)
    ).toBeVisible();
    expect(
      within(tooltip).getByText(/Centreon-Server: Round-Trip Average Time/)
    ).toBeVisible();
    expect(
      within(tooltip).getByText(/Centreon-Server: Round-Trip Minimum Time/)
    ).toBeVisible();
    expect(within(tooltip).getByText(/0.05 ms/)).toBeVisible();
    expect(within(tooltip).getByText(/0.02 ms/)).toBeVisible();
    expect(within(tooltip).getByText(/0.11 ms/)).toBeVisible();

    expect(screen.getByTestId('stacked-bar-3-0-0.16196')).toBeVisible();
  });

  (['horizontal', 'vertical'] as const).forEach((orientation) => {
    it(`displays the bar chart ${orientation}ly`, async () => {
      initialize({ orientation });
      const userData = renderHook(() => useAtomValue(userAtom));
      userData.result.current.locale = 'en';

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.08084')
      ).toBeVisible();
      expectBarDirection({ orientation, testId: 'stacked-bar-3-0-0.08084' });
      expect(screen.queryAllByText(/^-[\d.]+ ms$/)).toHaveLength(0);
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it(`displays the bar chart ${orientation}ly centered in zero`, async () => {
      initialize({ axis: { isCenteredZero: true }, orientation });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.08084')
      ).toBeVisible();
      expectBarDirection({ orientation, testId: 'stacked-bar-3-0-0.08084' });
      expect(screen.getAllByText(/^-[\d.]+ ms$/).length).toBeGreaterThan(0);
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it(`displays the stacked bar chart ${orientation}ly`, async () => {
      initialize({ data: dataPingServiceStacked, orientation });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.16196')
      ).toBeVisible();
      expectSameStack({
        orientation,
        testIdPrefixes: [
          'stacked-bar-1-0-',
          'stacked-bar-3-0-',
          'stacked-bar-4-0-'
        ]
      });
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data`, async () => {
      initialize({ data: dataPingServiceMixedStacked, orientation });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.08644')
      ).toBeVisible();
    });

    it(`displays the stacked bar chart ${orientation}ly centered in zero`, async () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceStacked,
        orientation
      });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.16196')
      ).toBeVisible();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data centered in zero`, async () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceMixedStacked,
        orientation
      });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.08644')
      ).toBeVisible();
    });

    it(`displays bar chart ${orientation}ly with a custom style`, async () => {
      initialize({
        barStyle: { opacity: 0.5, radius: 0.5 },
        data: dataPingServiceMixedStacked,
        orientation
      });

      await checkWidth();
      await checkAxes();

      expect(
        await screen.findByTestId('stacked-bar-3-0-0.08644')
      ).toBeVisible();
    });
  });

  it('displays only metrics with a defined value in the tooltip when the mode is set to defined', async () => {
    initialize({
      data: dataPingServiceStackedWithUndefinedValues,
      orientation: 'horizontal',
      tooltip: {
        mode: 'defined',
        sortOrder: 'name'
      }
    });

    await checkWidth();
    await expectVisibleText(/0 ms/);
    await expectVisibleText(/:40 AM/);

    await userEvent.hover(await screen.findByTestId('stacked-bar-1-0-0.05296'));

    const tooltip = await getTooltip();
    expect(within(tooltip).getByText(/06\/19\/2024/)).toBeVisible();

    expect(
      document.querySelector(
        '[data-metric="Centreon-Server: Round-Trip Average Time"]'
      )
    ).toBeVisible();
    expect(
      document.querySelector(
        '[data-metric="Centreon-Server: Round-Trip Maximum Time"]'
      )
    ).not.toBeInTheDocument();
    expect(
      document.querySelector(
        '[data-metric="Centreon-Server: Round-Trip Minimum Time"]'
      )
    ).not.toBeInTheDocument();
  });

  it('displays metrics with an undefined value in the tooltip when the mode is set to all', async () => {
    initialize({
      data: dataPingServiceStackedWithUndefinedValues,
      orientation: 'horizontal',
      tooltip: {
        mode: 'all',
        sortOrder: 'name'
      }
    });

    await checkWidth();
    await expectVisibleText(/0 ms/);
    await expectVisibleText(/:40 AM/);

    await userEvent.hover(await screen.findByTestId('stacked-bar-1-0-0.05296'));

    const tooltip = await getTooltip();
    expect(within(tooltip).getByText(/06\/19\/2024/)).toBeVisible();

    expect(
      document.querySelector(
        '[data-metric="Centreon-Server: Round-Trip Maximum Time"]'
      )
    ).toBeVisible();
    expect(
      document.querySelector(
        '[data-metric="Centreon-Server: Round-Trip Minimum Time"]'
      )
    ).toBeVisible();
  });

  it('does not display a tooltip when a bar is hovered and a props is set', async () => {
    initialize({
      data: dataPingServiceStacked,
      orientation: 'horizontal',
      tooltip: {
        mode: 'hidden',
        sortOrder: 'descending'
      }
    });

    await checkWidth();
    await checkAxes();

    await userEvent.hover(await screen.findByTestId('stacked-bar-1-0-0.05296'));

    expect(screen.queryByText(/06\/19\/2024/)).not.toBeInTheDocument();

    expect(
      screen.getByTestId('stacked-bar-3-0-0.12340000000000001')
    ).toBeVisible();
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays the bottom axis correctly when data starts from several days ago', async () => {
    initialize({
      data: dataLastWeek,
      orientation: 'horizontal'
    });

    await expectVisibleText(/05\/31\/2023/);
    await expectVisibleText(/06\/07\/2023/);
  });

  it('displays the bar chart according to min and max boundaries', async () => {
    initialize({
      data: dataLastWeek,
      max: 1,
      min: -0.05
    });

    await expectVisibleText(/05\/31\/2023/);
    await expectVisibleText(/06\/07\/2023/);
    await expectVisibleText(/1 s/);
    await expectVisibleText(/1%/);
  });

  it('displays the stacked bar chart correctly when a point is missing compare to the time serie', async () => {
    initialize({ data: dataMissingPoint });

    const lastBar = await screen.findByTestId('stacked-bar-2-0-139');
    expect(lastBar).toBeVisible();
    expect(lastBar.getBoundingClientRect().bottom).toBeCloseTo(
      getBar('stacked-bar-1-0-').getBoundingClientRect().bottom,
      0
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays the stacked bar chart with bars stacked together', async () => {
    initialize({ data: dataPingServiceLinesStackKeys });

    expect(await screen.findByTestId('stacked-bar-3-0-0.05336')).toBeVisible();
    expect(screen.getByTestId('stacked-bar-4-0-0.06684')).toBeVisible();
    expectSameStack({
      orientation: 'horizontal',
      testIdPrefixes: ['stacked-bar-3-0-', 'stacked-bar-4-0-']
    });
    expect(getBar('stacked-bar-1-0-').getBoundingClientRect().left).not.toBe(
      getBar('stacked-bar-3-0-').getBoundingClientRect().left
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('does not displays corresponding calculations when props are set', async () => {
    initialize({
      data: dataLastWeek,
      legend: {
        mode: 'grid',
        placement: 'bottom',
        showCalculations: {
          avg: false,
          max: false,
          min: true
        }
      },
      orientation: 'horizontal'
    });

    await expectVisibleText(new RegExp(labelMin));
    expect(screen.queryAllByText(new RegExp(labelMax))).toHaveLength(0);
    expect(screen.queryAllByText(new RegExp(labelAvg))).toHaveLength(0);
  });
});

describe('Bar chart time areas', () => {
  const timeAreas = [
    {
      backgroundColor: 'rgb(255, 0, 0)',
      end: '2024-06-19T11:30:00+02:00',
      start: '2024-06-19T11:10:00+02:00'
    }
  ];

  it('draws a time area along the time axis of a horizontal bar chart', async () => {
    initialize({ orientation: 'horizontal', timeAreas });

    const area = (await screen.findByTestId('time-area')).querySelector('rect');

    expect(area).toHaveAttribute('y', '0');
    expect(Number(area?.getAttribute('x'))).toBeGreaterThan(0);
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('draws a time area along the time axis of a vertical bar chart', async () => {
    initialize({ orientation: 'vertical', timeAreas });

    const area = (await screen.findByTestId('time-area')).querySelector('rect');

    expect(area).toHaveAttribute('x', '0');
    expect(Number(area?.getAttribute('y'))).toBeGreaterThan(0);
    expect(getNormalizedChart()).toMatchSnapshot();
  });
});
