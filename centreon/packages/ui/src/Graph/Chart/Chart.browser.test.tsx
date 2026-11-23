import { type User, userAtom } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';
import { always, equals } from 'ramda';
import { type ComponentProps, useState } from 'react';

import type { LineChartData } from '../common/models';
import { getNormalizedChart } from '../common/testUtils';
import dataCurvesWithSameColor from '../mockedData/curvesWithSameColor.json';
import dataLastDay from '../mockedData/lastDay.json';
import dataLastDayWithIncompleteValues from '../mockedData/lastDayWithIncompleteValues.json';
import dataLastDayWithNullValues from '../mockedData/lastDayWithNullValues.json';
import dataPingServiceLines from '../mockedData/pingService.json';
import dataPingServiceLinesBars from '../mockedData/pingServiceLinesBars.json';
import dataPingServiceLinesBarsMixed from '../mockedData/pingServiceLinesBarsMixed.json';
import dataPingServiceLinesBarsStacked from '../mockedData/pingServiceLinesBarsStacked.json';
import WrapperChart from '.';
import { args as argumentsData } from './helpers/doc';
import type { LineChartProps } from './models';
import { labelAvg, labelMin } from './translatedLabels';

const dataLastDayWithZeroConnTime = {
  ...dataLastDay,
  metrics: dataLastDay.metrics.map((metric) =>
    equals(metric.metric, 'connTime')
      ? { ...metric, data: metric.data.map(always(0)) }
      : metric
  )
};

interface Props
  extends Pick<
    LineChartProps,
    | 'legend'
    | 'tooltip'
    | 'axis'
    | 'lineStyle'
    | 'barStyle'
    | 'additionalLines'
    | 'min'
    | 'max'
    | 'boundariesUnit'
    | 'timeAreas'
  > {
  data?: object;
}

// Cypress `contains`: the deepest elements whose text includes the value.
const containing =
  (text: string | RegExp) =>
  (_: string, element: Element | null): boolean => {
    const matches = (node: Element): boolean =>
      typeof text === 'string'
        ? Boolean(node.textContent?.includes(text))
        : text.test(node.textContent ?? '');

    return Boolean(
      element && matches(element) && !Array.from(element.children).some(matches)
    );
  };

const textElement = (text: string | RegExp): HTMLElement | undefined =>
  screen.queryAllByText(containing(text))[0];

const expectVisible = (getElement: () => Element | null | undefined) =>
  waitFor(() => expect(getElement()).toBeVisible());

const expectTextVisible = (text: string | RegExp) =>
  expectVisible(() => textElement(text));

const element = (selector: string): Element | null =>
  document.querySelector(selector);

const checkLegendInformation = async (): Promise<void> => {
  await expectTextVisible('hitratio');
  await expectTextVisible('querytime');
  await expectTextVisible('connTime');
  await expectTextVisible('Min: 70.31');
  await expectTextVisible('Min: 0.03');
  await expectTextVisible('Max: 88.03');
  await expectTextVisible('Max: 0.98');
  await expectTextVisible('Max: 0.97');
  await expectTextVisible('Avg: 78.07');
  await expectTextVisible('Avg: 0.5');
  await expectTextVisible('Avg: 0.51');
};

const CustomUnitComponent = (
  props: ComponentProps<typeof WrapperChart>
): JSX.Element => {
  const [leftUnit, setLeftUnit] = useState('%');
  const [rightUnit, setRightUnit] = useState('ms');

  return (
    <WrapperChart
      {...props}
      axis={{
        axisYLeft: {
          onUnitChange: setLeftUnit,
          unit: leftUnit
        },
        axisYRight: {
          onUnitChange: setRightUnit,
          unit: rightUnit
        }
      }}
    />
  );
};

const createUserStore = (): ReturnType<typeof createStore> => {
  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris'
  } as unknown as User);

  return store;
};

const initialize = ({
  data = dataLastDay,
  tooltip,
  legend,
  axis,
  lineStyle,
  barStyle,
  additionalLines,
  min,
  max,
  boundariesUnit,
  timeAreas
}: Props): void => {
  render(
    <Provider store={createUserStore()}>
      <WrapperChart
        {...argumentsData}
        additionalLines={additionalLines}
        axis={axis}
        barStyle={barStyle}
        boundariesUnit={boundariesUnit}
        data={data as unknown as LineChartData}
        legend={legend}
        lineStyle={lineStyle}
        max={max}
        min={min}
        timeAreas={timeAreas}
        tooltip={tooltip}
      />
    </Provider>
  );
};

const initializeCustomUnits = ({
  data = dataLastDay,
  tooltip,
  legend,
  axis,
  lineStyle
}: Props): void => {
  render(
    <Provider store={createUserStore()}>
      <CustomUnitComponent
        {...argumentsData}
        axis={axis}
        data={data as unknown as LineChartData}
        legend={legend}
        lineStyle={lineStyle}
        tooltip={tooltip}
      />
    </Provider>
  );
};

const checkGraphWidth = async (): Promise<void> => {
  await waitFor(() => {
    const graph = screen.getByTestId('graph-interaction-zone');

    expect(graph).toHaveAttribute('height', '392');
    expect(Number(graph.getAttribute('width'))).toBeGreaterThan(1149);
  });
};

// cypress-real-events `realMouseMove(x, y)`: x and y from the zone's top-left
// corner. A real pointer also sends `mouseover`, which opens the MUI tooltip.
const moveMouse = (x: number, y: number): void => {
  const zone = screen.getByTestId('graph-interaction-zone');
  const { left, top } = zone.getBoundingClientRect();
  const position = { clientX: left + x, clientY: top + y };

  fireEvent.mouseOver(zone, position);
  fireEvent.mouseMove(zone, position);
};

// Cypress `.parent().click()` clicks the select the parent wraps.
const openUnitSelector = async (index: number): Promise<void> => {
  const selector = screen.getAllByTestId('unit-selector')[index]
    .parentElement as HTMLElement;

  await userEvent.click(within(selector).getByRole('combobox'));
};

const tooltipMetrics = (): Array<string | null> =>
  Array.from(
    document.querySelectorAll(
      '[data-metric="querytime"], [data-metric="connTime"], [data-metric="hitratio"]'
    )
  ).map((row) => row.getAttribute('data-metric'));

const isStepPath = (path: Element | null): boolean => {
  const points = (path?.getAttribute('d') ?? '')
    .split(/[MLZ]/)
    .filter(Boolean)
    .map((point) => point.split(',').map(Number));

  return points
    .slice(1)
    .every(([x, y], index) => x === points[index][0] || y === points[index][1]);
};

beforeEach(() => {
  expect.hasAssertions();
});

describe('Line chart', () => {
  describe('Tooltip', () => {
    it('displays a tooltip when the graph is hovered', async () => {
      initialize({});

      await checkGraphWidth();

      await expectTextVisible(
        'oracle-buffer-hit-ratio graph on srv-oracle-users'
      );
      await expectTextVisible('hitratio');
      await expectTextVisible('querytime');
      await expectTextVisible('connTime');
      await expectTextVisible('Min: 70.31');

      moveMouse(240, 70);

      await expectTextVisible('06/18/2023');
      await waitFor(() =>
        expect(tooltipMetrics()).toEqual(['connTime', 'hitratio', 'querytime'])
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays a metric highlighted when the graph is hovered and the metric is the nearest point', async () => {
      initialize({});

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(230, 26);

      await waitFor(() =>
        expect(element('[data-metric="querytime"]')).toHaveAttribute(
          'data-highlight',
          'false'
        )
      );
      expect(element('[data-metric="connTime"]')).toHaveAttribute(
        'data-highlight',
        'true'
      );
      expect(element('[data-metric="hitratio"]')).toHaveAttribute(
        'data-highlight',
        'false'
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('does not display the tooltip when null values are hovered', async () => {
      initialize({ data: dataLastDayWithNullValues });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(1205, 100);

      expect(element('[data-metric="querytime"]')).not.toBeInTheDocument();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays the tooltip with defined values when the graph is hovered', async () => {
      initialize({ data: dataLastDayWithIncompleteValues });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(1100, 100);

      await expectVisible(() => element('[data-metric="querytime"]'));
      await expectVisible(() => element('[data-metric="hitratio"]'));
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('does not display the tooltip when the corresponding prop is set', async () => {
      initialize({ tooltip: { mode: 'hidden', sortOrder: 'name' } });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(452, 26);

      expect(element('[data-metric="querytime"]')).not.toBeInTheDocument();
      expect(element('[data-metric="connTime"]')).not.toBeInTheDocument();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('sorts metrics by their value is ascending when the corresponding prop is set', async () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'ascending' } });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(452, 26);

      await expectVisible(() => element('[data-metric="querytime"]'));
      await expectVisible(() => element('[data-metric="connTime"]'));
      expect(tooltipMetrics()).toEqual(['querytime', 'connTime', 'hitratio']);
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('sorts metrics by their value is descending when the corresponding prop is set', async () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'descending' } });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(452, 26);

      await expectVisible(() => element('[data-metric="querytime"]'));
      await expectVisible(() => element('[data-metric="connTime"]'));
      expect(tooltipMetrics()).toEqual(['hitratio', 'connTime', 'querytime']);
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays only metrics with a defined value when the mode is set to defined', async () => {
      initialize({
        data: dataLastDayWithZeroConnTime,
        tooltip: { mode: 'defined', sortOrder: 'name' }
      });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(452, 26);

      await expectVisible(() => element('[data-metric="querytime"]'));
      expect(element('[data-metric="connTime"]')).not.toBeInTheDocument();
    });

    it('displays metrics with a zero value when the mode is set to all', async () => {
      initialize({
        data: dataLastDayWithZeroConnTime,
        tooltip: { mode: 'all', sortOrder: 'name' }
      });

      await checkGraphWidth();

      await expectTextVisible('Min: 70.31');

      moveMouse(452, 26);

      await expectVisible(() => element('[data-metric="querytime"]'));
      await expectVisible(() => element('[data-metric="connTime"]'));
    });
  });

  it('displays the curves with different shades when curves have same color', async () => {
    initialize({ data: dataCurvesWithSameColor });

    await waitFor(() =>
      expect(screen.getByTestId('graph-interaction-zone')).toHaveAttribute(
        'width',
        '1220'
      )
    );

    const icons = document.querySelectorAll('[data-icon="true"]');
    const iconColors = [
      'rgb(41, 175, 238)',
      'rgb(83, 191, 241)',
      'rgb(8, 34, 47)',
      'rgb(16, 70, 95)',
      'rgb(24, 105, 142)',
      'rgb(32, 140, 190)'
    ];

    iconColors.forEach((color, index) => {
      expect(getComputedStyle(icons[index]).backgroundColor).toBe(color);
    });

    expect(element('[data-metric="1"]')).toHaveAttribute(
      'stroke',
      'rgb(41, 175, 238)'
    );
    expect(element('[data-metric="21"]')).toHaveAttribute(
      'stroke',
      'rgb(32, 140, 190)'
    );
    expect(element('[data-metric="17"]')).toHaveAttribute(
      'stroke',
      'rgb(24, 105, 142)'
    );
    expect(element('[data-metric="13"]')).toHaveAttribute(
      'stroke',
      'rgb(16, 70, 95)'
    );
    expect(element('[data-metric="9"]')).toHaveAttribute(
      'stroke',
      'rgb(8, 34, 47)'
    );
    expect(element('[data-metric="5"]')).toHaveAttribute(
      'stroke',
      'rgb(83, 191, 241)'
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  describe('Legend', () => {
    it('displays the legend in list mode when the corresponding props is set', async () => {
      initialize({ legend: { mode: 'list', placement: 'bottom' } });

      await expectTextVisible(':00 AM');

      expect(screen.queryAllByText(containing('Min:'))).toHaveLength(0);
      expect(screen.queryAllByText(containing('Max:'))).toHaveLength(0);
      expect(screen.queryAllByText(containing('Avg:'))).toHaveLength(0);

      expect(element('[data-display-side="false"]')).toBeInTheDocument();
      expect(element('[data-as-list="true"]')).toBeInTheDocument();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays the legend on the left side of the graph when the corresponding prop is set', async () => {
      initialize({
        legend: { mode: 'grid', placement: 'left' }
      });

      await expectTextVisible(':00 AM');

      expect(element('[data-display-side="true"]')).toBeInTheDocument();
      expect(element('[data-as-list="true"]')).toBeInTheDocument();
    });

    it('displays the legend on the right side of the graph as list when the corresponding props are set', async () => {
      initialize({ legend: { mode: 'list', placement: 'right' } });

      await expectTextVisible(':00 AM');

      expect(element('[data-display-side="true"]')).toBeInTheDocument();
      expect(element('[data-as-list="true"]')).toBeInTheDocument();
    });
  });

  describe('Axis', () => {
    it('does not display axis borders when the prop is set', async () => {
      initialize({ axis: { showBorder: false } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      const axisLines = document.querySelectorAll(
        'line[class*="visx-axis-line"]'
      );

      expect(axisLines[0]).toHaveAttribute('stroke-width', '0');
      expect(axisLines[1]).toHaveAttribute('stroke-width', '0');
      expect(axisLines[2]).toHaveAttribute('stroke-width', '0');
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('does not display grids when the prop is set', async () => {
      initialize({ axis: { showGridLines: false } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      expect(
        element('g[class="visx-group visx-rows"]')
      ).not.toBeInTheDocument();
      expect(
        element('g[class="visx-group visx-columns"]')
      ).not.toBeInTheDocument();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays only horizontal lines when the prop is set', async () => {
      initialize({ axis: { gridLinesType: 'horizontal' } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      await expectVisible(() => element('g[class="visx-group visx-rows"]'));
      expect(
        element('g[class="visx-group visx-columns"]')
      ).not.toBeInTheDocument();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays only vertical lines when the prop is set', async () => {
      initialize({ axis: { gridLinesType: 'vertical' } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      expect(
        element('g[class="visx-group visx-rows"]')
      ).not.toBeInTheDocument();
      await expectVisible(() => element('g[class="visx-group visx-columns"]'));
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('rotates the tick label when the props is set', async () => {
      initialize({ axis: { yAxisTickLabelRotation: -35 } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      await expectVisible(() =>
        element('text[transform="rotate(-35, -2, 187.52600691899028)"]')
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays as centered to zero when the prop is set', async () => {
      initialize({ axis: { isCenteredZero: true } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');

      await expectTextVisible('0.8');
      await expectTextVisible('-0.8');
      expect(getNormalizedChart()).toMatchSnapshot();
    });
  });

  describe('Line style', () => {
    it('displays the curve in a natural style when the prop is set', async () => {
      initialize({ lineStyle: { curve: 'natural' } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      await expectVisible(() => element('[data-metric="13536"]'));
      await expectVisible(() => element('[data-metric="13534"]'));
      await expectVisible(() => element('[data-metric="13535"]'));
      expect(element('[data-metric="13536"]')?.getAttribute('d')).toContain(
        'C'
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays the curve in a step style when the prop is set', async () => {
      initialize({ data: dataPingServiceLines, lineStyle: { curve: 'step' } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      await expectVisible(() => element('[data-metric="1"]'));
      await expectVisible(() => element('[data-metric="2"]'));
      await expectVisible(() => element('[data-metric="3"]'));
      expect(isStepPath(element('path[data-metric="2"]'))).toBe(true);
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('shows the area when the prop is set', async () => {
      initialize({ lineStyle: { showArea: true } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      await expectVisible(() =>
        element('path[fill="rgba(102, 153, 204, 0.19999999999999996)"]')
      );

      await expectVisible(() => element('[data-metric="13536"]'));
      await expectVisible(() => element('[data-metric="13534"]'));
      await expectVisible(() => element('[data-metric="13535"]'));

      await checkLegendInformation();
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('shows the area with a custom transparency when props are set', async () => {
      initialize({ lineStyle: { areaTransparency: 20, showArea: true } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      await expectVisible(() =>
        element('path[fill="rgba(102, 153, 204, 0.8)"]')
      );
    });

    it('shows points when the prop is set', async () => {
      initialize({ lineStyle: { showPoints: true } });

      await checkGraphWidth();
      await expectTextVisible(':00 AM');
      await waitFor(() =>
        expect(document.querySelectorAll('circle')).toHaveLength(864)
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays lines with a custom line width when the prop is set', async () => {
      initialize({ lineStyle: { lineWidth: 6 } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      expect(document.querySelectorAll('path[stroke-width="6"]')).toHaveLength(
        3
      );
      expect(getNormalizedChart()).toMatchSnapshot();
    });

    it('displays lines with dots width when the prop is set', async () => {
      initialize({ lineStyle: { dotOffset: 10, lineWidth: 4 } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      expect(element('path[stroke-width="4"]')).toHaveAttribute(
        'stroke-dasharray',
        '4 10'
      );
    });

    it('displays lines with dashes width when props are set', async () => {
      initialize({ lineStyle: { dashLength: 5, dashOffset: 8 } });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      expect(element('path[stroke-width="2"]')).toHaveAttribute(
        'stroke-dasharray',
        '5 8'
      );
    });

    it('displays only one line with custom style when props are set', async () => {
      initialize({
        lineStyle: [
          {
            dashLength: 5,
            dashOffset: 4,
            lineWidth: 1,
            metricId: 13534,
            showArea: true,
            showPoints: true
          }
        ]
      });

      await checkGraphWidth();

      await expectTextVisible(':00 AM');
      expect(element('path.visx-area-closed')).toHaveAttribute(
        'stroke-dasharray',
        '5 4'
      );
      expect(document.querySelectorAll('circle')).toHaveLength(288);
      expect(getNormalizedChart()).toMatchSnapshot();
    });
  });
});

describe('Time areas', () => {
  it('draws each time area with its background, pattern and label, clamped to the chart', async () => {
    initialize({
      timeAreas: [
        {
          backgroundColor: 'rgb(255, 0, 0)',
          end: '2023-06-18T23:00:00+02:00',
          label: 'Purged',
          pattern: {
            height: 6,
            orientation: ['diagonal'],
            stroke: 'rgb(0, 0, 255)',
            width: 6
          },
          start: '2023-06-18T12:00:00+02:00'
        },
        {
          backgroundColor: 'rgb(0, 128, 0)',
          end: '2023-06-19T10:00:00+02:00',
          start: '2023-06-19T08:00:00+02:00'
        },
        {
          backgroundColor: 'rgb(0, 0, 0)',
          end: '2023-06-20T05:00:00+02:00',
          start: '2023-06-20T00:00:00+02:00'
        }
      ]
    });

    const [purged, other] = await screen.findAllByTestId('time-area');

    expect(screen.getAllByTestId('time-area')).toHaveLength(2);

    expect(purged.querySelector('rect[fill="rgb(255, 0, 0)"]')).toHaveAttribute(
      'x',
      '0'
    );
    expect(purged.querySelector('pattern path')).toHaveAttribute(
      'stroke',
      'rgb(0, 0, 255)'
    );
    expect(
      purged.querySelector('rect[fill^="url(#time-area-"]')
    ).toBeInTheDocument();
    await expectVisible(() => within(purged).getByText('Purged'));

    const otherRects = other.querySelectorAll('rect');

    expect(otherRects).toHaveLength(1);
    expect(otherRects[0]).toHaveAttribute('fill', 'rgb(0, 128, 0)');
    expect(getNormalizedChart()).toMatchSnapshot();
  });
});

describe('Lines and bars', () => {
  it('displays lines and bars in the same chart', async () => {
    initialize({
      data: dataPingServiceLinesBars
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-7650.368581547736')
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays stacked lines and bars in the same chart', async () => {
    initialize({
      data: dataPingServiceLinesBarsStacked
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() => screen.queryByTestId('stacked-bar-2-0-6835'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-14920.328518673756')
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays mixed lines and bars in the same chart', async () => {
    initialize({
      data: dataPingServiceLinesBarsMixed
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-7650.368581547736')
    );
    await expectVisible(() => screen.queryByTestId('stacked-bar-2-0-10'));
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays lines and bars in the same chart centered in zero', async () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBars
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-7650.368581547736')
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays stacked lines and bars in the same chart centered in zero', async () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBarsStacked
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() => screen.queryByTestId('stacked-bar-2-0-6835'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-14920.328518673756')
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays mixed lines and bars in the same chart centered in zero', async () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBarsMixed
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-7650.368581547736')
    );
    await expectVisible(() => screen.queryByTestId('stacked-bar-2-0-10'));
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('changes the unit on the left or right scales when a new unit is selected', async () => {
    initializeCustomUnits({
      data: dataPingServiceLinesBarsMixed
    });

    await checkGraphWidth();

    await openUnitSelector(0);
    await userEvent.click(await screen.findByLabelText('B'));

    await waitFor(() =>
      expect(screen.getAllByTestId('unit-selector')[0]).toHaveValue('B')
    );
    await expectTextVisible('8.79 KiB');

    await openUnitSelector(1);
    await userEvent.click(await screen.findByLabelText('%'));

    await waitFor(() =>
      expect(screen.getAllByTestId('unit-selector')[1]).toHaveValue('%')
    );
    await expectTextVisible('20');
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays stacked lines and bars when a line and a bar are customized', async () => {
    initialize({
      barStyle: [
        {
          metricId: 10,
          opacity: 0.5,
          radius: 0.3
        }
      ],
      data: dataPingServiceLinesBarsStacked,
      lineStyle: [
        {
          dotOffset: 4,
          lineWidth: 3,
          metricId: 1,
          showArea: false
        }
      ]
    });

    await checkGraphWidth();

    await expectVisible(() => screen.queryByTestId('stacked-bar-2-0-1243'));
    await expectVisible(() =>
      screen.queryByTestId('stacked-bar-10-0-8520.53622137828')
    );
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays additional lines when props are set', async () => {
    initialize({
      additionalLines: [
        { color: 'pink', unit: '%', yValue: 3 },
        { color: 'red', text: 'some text', unit: 'ms', yValue: 0.15 }
      ],
      data: dataPingServiceLines
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));

    await expectTextVisible('some text');
    expect(screen.getByTestId('pink-3')).toBeInTheDocument();
    expect(screen.getByTestId('red-0.15')).toBeInTheDocument();
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays graph according to min and max boundaries', async () => {
    initialize({
      data: dataPingServiceLines,
      max: 0.1,
      min: 0.01
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));

    await expectTextVisible('0.1 ms');
    await expectTextVisible('0.1%');
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('displays graph according to min and max boundaries for a unit', async () => {
    initialize({
      boundariesUnit: 'ms',
      data: dataPingServiceLines,
      max: 0.1,
      min: 0.01
    });

    await checkGraphWidth();

    await expectVisible(() => element('path[data-metric="1"]'));
    await expectVisible(() => element('path[data-metric="3"]'));

    await expectTextVisible('0.1 ms');
    await expectTextVisible('2%');
    expect(getNormalizedChart()).toMatchSnapshot();
  });

  it('calls the secondary function when a metric is clicked in the legend', async () => {
    const secondaryClick = rs.fn();
    initialize({
      data: dataPingServiceLines,
      legend: {
        mode: 'grid',
        placement: 'bottom',
        secondaryClick
      }
    });

    await checkGraphWidth();

    await userEvent.pointer({
      keys: '[MouseRight]',
      target: textElement('Packet Loss') as HTMLElement
    });
    expect(secondaryClick).toHaveBeenCalled();
  });

  it('does not displays corresponding calculations when props are set', async () => {
    initialize({
      data: dataPingServiceLines,
      legend: {
        mode: 'grid',
        placement: 'bottom',
        showCalculations: {
          avg: false,
          max: false,
          min: true
        }
      }
    });

    await expectTextVisible(labelMin);
    expect(screen.queryAllByText(containing(/^Max$/))).toHaveLength(0);
    expect(screen.queryAllByText(containing(labelAvg))).toHaveLength(0);
  });
});
