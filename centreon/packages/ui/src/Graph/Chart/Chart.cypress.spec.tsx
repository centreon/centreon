import { useState } from 'react';

import { Provider, createStore } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import { LineChartData } from '../common/models';
import dataCurvesWithSameColor from '../mockedData/curvesWithSameColor.json';
import dataLastDay from '../mockedData/lastDay.json';
import dataLastDayWithIncompleteValues from '../mockedData/lastDayWithIncompleteValues.json';
import dataLastDayWithNullValues from '../mockedData/lastDayWithNullValues.json';
import dataPingServiceLines from '../mockedData/pingService.json';
import dataPingServiceLinesBars from '../mockedData/pingServiceLinesBars.json';
import dataPingServiceLinesBarsMixed from '../mockedData/pingServiceLinesBarsMixed.json';
import dataPingServiceLinesBarsStacked from '../mockedData/pingServiceLinesBarsStacked.json';

import { args as argumentsData } from './helpers/doc';
import { LineChartProps } from './models';

import WrapperChart from '.';

interface Props
  extends Pick<
    LineChartProps,
    'legend' | 'tooltip' | 'axis' | 'lineStyle' | 'barStyle' | 'additionalLines'
  > {
  data?: LineChartData;
}

const checkLegendInformation = (): void => {
  cy.contains('hitratio').should('be.visible');
  cy.contains('querytime').should('be.visible');
  cy.contains('connTime').should('be.visible');
  cy.contains('Min: 70.31').should('be.visible');
  cy.contains('Min: 0.03').should('be.visible');
  cy.contains('Max: 88.03').should('be.visible');
  cy.contains('Max: 0.98').should('be.visible');
  cy.contains('Max: 0.97').should('be.visible');
  cy.contains('Avg: 78.07').should('be.visible');
  cy.contains('Avg: 0.5').should('be.visible');
  cy.contains('Avg: 0.51').should('be.visible');
};

const CustomUnitComponent = (props): JSX.Element => {
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

const initialize = ({
  data = dataLastDay,
  tooltip,
  legend,
  axis,
  lineStyle,
  barStyle,
  additionalLines
}: Props): void => {
  cy.adjustViewport();

  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris'
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <WrapperChart
          {...argumentsData}
          axis={axis}
          data={data as unknown as LineChartData}
          legend={legend}
          lineStyle={lineStyle}
          barStyle={barStyle}
          tooltip={tooltip}
          additionalLines={additionalLines}
        />
      </Provider>
    )
  });

  cy.viewport('macbook-13');
};

const initializeCustomUnits = ({
  data = dataLastDay,
  tooltip,
  legend,
  axis,
  lineStyle
}: Props): void => {
  cy.adjustViewport();

  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris'
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <CustomUnitComponent
          {...argumentsData}
          axis={axis}
          data={data as unknown as LineChartData}
          legend={legend}
          lineStyle={lineStyle}
          tooltip={tooltip}
        />
      </Provider>
    )
  });

  cy.viewport('macbook-13');
};

const checkGraphWidth = (): void => {
  cy.findByTestId('graph-interaction-zone')
    .should('have.attr', 'height')
    .and('equal', '376.203125');

  cy.findByTestId('graph-interaction-zone').then((graph) => {
    expect(Number(graph[0].attributes.width.value)).to.be.greaterThan(1170);
  });
};

describe('Line chart', () => {
  describe('Tooltip', () => {
    it('displays a tooltip when the graph is hovered', () => {
      initialize({});

      checkGraphWidth();

      cy.contains('oracle-buffer-hit-ratio graph on srv-oracle-users').should(
        'be.visible'
      );
      cy.contains('hitratio').should('be.visible');
      cy.contains('querytime').should('be.visible');
      cy.contains('connTime').should('be.visible');
      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(250, 70);

      cy.contains('06/18/2023').should('be.visible');

      cy.contains('0.45 s').should('be.visible');
      cy.contains('73.65%').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays a metric highlighted when the graph is hovered and the metric is the nearest point', () => {
      initialize({});

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(230, 26);

      cy.get('[data-metric="querytime"]').should(
        'have.attr',
        'data-highlight',
        'false'
      );
      cy.get('[data-metric="connTime"]').should(
        'have.attr',
        'data-highlight',
        'false'
      );
      cy.get('[data-metric="hitratio"]').should(
        'have.attr',
        'data-highlight',
        'true'
      );

      cy.makeSnapshot();
    });

    it('does not display the tooltip when null values are hovered', () => {
      initialize({ data: dataLastDayWithNullValues });

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1198, 100);

      cy.get('[data-metric="querytime"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the tooltip with defined values when the graph is hovered', () => {
      initialize({ data: dataLastDayWithIncompleteValues });

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1170, 100);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="hitratio"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('does not display the tooltip when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'hidden', sortOrder: 'name' } });

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('not.exist');
      cy.get('[data-metric="connTime"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('sorts metrics by their value is ascending when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'ascending' } });

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="connTime"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('sorts metrics by their value is descending when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'descending' } });

      checkGraphWidth();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="connTime"]').should('be.visible');

      cy.makeSnapshot();
    });
  });

  it('displays the curves with different shades when curves have same color', () => {
    initialize({ data: dataCurvesWithSameColor });

    cy.findByTestId('graph-interaction-zone')
      .should('have.attr', 'width')
      .and('equal', '1200');

    cy.findByLabelText('Centreon-Server: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(41, 175, 238)');
    cy.findByLabelText('Centreon-Server_5: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(83, 191, 241)');
    cy.findByLabelText('Centreon-Server_4: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(8, 34, 47)');
    cy.findByLabelText('Centreon-Server_3: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(16, 70, 95)');
    cy.findByLabelText('Centreon-Server_2: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(24, 105, 142)');
    cy.findByLabelText('Centreon-Server_1: Round-Trip Average Time')
      .find('[data-icon="true"]')
      .should('have.css', 'background-color', 'rgb(32, 140, 190)');

    cy.get('[data-metric="1"]').should(
      'have.attr',
      'stroke',
      'rgb(41, 175, 238)'
    );
    cy.get('[data-metric="21"]').should(
      'have.attr',
      'stroke',
      'rgb(32, 140, 190)'
    );
    cy.get('[data-metric="17"]').should(
      'have.attr',
      'stroke',
      'rgb(24, 105, 142)'
    );
    cy.get('[data-metric="13"]').should(
      'have.attr',
      'stroke',
      'rgb(16, 70, 95)'
    );
    cy.get('[data-metric="9"]').should('have.attr', 'stroke', 'rgb(8, 34, 47)');
    cy.get('[data-metric="5"]').should(
      'have.attr',
      'stroke',
      'rgb(83, 191, 241)'
    );

    cy.makeSnapshot();
  });

  describe('Legend', () => {
    it('displays the legend in list mode when the corresponding props is set', () => {
      initialize({ legend: { mode: 'list', placement: 'bottom' } });

      cy.contains('Min:').should('not.exist');
      cy.contains('Max:').should('not.exist');
      cy.contains('Avg:').should('not.exist');

      cy.get('[data-display-side="false"]').should('exist');
      cy.get('[data-as-list="true"]').should('exist');

      cy.contains(':00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the legend on the left side of the graph when the corresponding prop is set', () => {
      initialize({
        legend: { mode: 'grid', placement: 'left' }
      });

      cy.get('[data-display-side="true"]').should('exist');
      cy.get('[data-as-list="true"]').should('exist');

      cy.contains(':00 AM').should('be.visible');
    });

    it('displays the legend on the right side of the graph as list when the corresponding props are set', () => {
      initialize({ legend: { mode: 'list', placement: 'right' } });

      cy.get('[data-display-side="true"]').should('exist');
      cy.get('[data-as-list="true"]').should('exist');

      cy.contains(':00 AM').should('be.visible');
    });
  });

  describe('Axis', () => {
    it('does not display axis borders when the prop is set', () => {
      initialize({ axis: { showBorder: false } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.get('line[class*="visx-axis-line"]')
        .eq(0)
        .should('have.attr', 'stroke-width')
        .and('equal', '0');
      cy.get('line[class*="visx-axis-line"]')
        .eq(1)
        .should('have.attr', 'stroke-width')
        .and('equal', '0');
      cy.get('line[class*="visx-axis-line"]')
        .eq(2)
        .should('have.attr', 'stroke-width')
        .and('equal', '0');

      cy.makeSnapshot();
    });

    it('does not display grids when the prop is set', () => {
      initialize({ axis: { showGridLines: false } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('not.exist');
      cy.get('g[class="visx-group visx-columns"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays only horizontal lines when the prop is set', () => {
      initialize({ axis: { gridLinesType: 'horizontal' } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('be.visible');
      cy.get('g[class="visx-group visx-columns"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays only vertical lines when the prop is set', () => {
      initialize({ axis: { gridLinesType: 'vertical' } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('not.exist');
      cy.get('g[class="visx-group visx-columns"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('rotates the tick label when the props is set', () => {
      initialize({ axis: { yAxisTickLabelRotation: -35 } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.get('text[transform="rotate(-35, -2, 145.04834208635688)"]').should(
        'be.visible'
      );

      cy.makeSnapshot();
    });

    it('displays as centered to zero when the prop is set', () => {
      initialize({ axis: { isCenteredZero: true } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');

      cy.contains('0.9').should('be.visible');
      cy.contains('-0.9').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Line style', () => {
    it('displays the curve in a natural style when the prop is set', () => {
      initialize({ lineStyle: { curve: 'natural' } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('[data-metric="13536"]').should('be.visible');
      cy.get('[data-metric="13534"]').should('be.visible');
      cy.get('[data-metric="13535"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the curve in a step style when the prop is set', () => {
      initialize({ lineStyle: { curve: 'step' }, data: dataPingServiceLines });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('[data-metric="1"]').should('be.visible');
      cy.get('[data-metric="2"]').should('be.visible');
      cy.get('[data-metric="3"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('shows the area when the prop is set', () => {
      initialize({ lineStyle: { showArea: true } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path[fill="rgba(102, 153, 204, 0.19999999999999996)"]').should(
        'be.visible'
      );

      cy.get('[data-metric="13536"]').should('be.visible');
      cy.get('[data-metric="13534"]').should('be.visible');
      cy.get('[data-metric="13535"]').should('be.visible');

      checkLegendInformation();

      cy.makeSnapshot();
    });

    it('shows the area with a custom transparency when props are set', () => {
      initialize({ lineStyle: { areaTransparency: 20, showArea: true } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path[fill="rgba(102, 153, 204, 0.8)"]').should('be.visible');
    });

    it('shows points when the prop is set', () => {
      initialize({ lineStyle: { showPoints: true } });

      checkGraphWidth();
      cy.contains(':00 AM').should('be.visible');
      cy.get('circle[cx="250.83333333333334"]').should('be.visible');
      cy.get('circle[cy="52.93597418085514"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays lines with a custom line width when the prop is set', () => {
      initialize({ lineStyle: { lineWidth: 6 } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path[stroke-width="6"]').should('have.length', 3);

      cy.makeSnapshot();
    });

    it('displays lines with dots width when the prop is set', () => {
      initialize({ lineStyle: { dotOffset: 10, lineWidth: 4 } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path[stroke-width="4"]')
        .should('have.attr', 'stroke-dasharray')
        .and('equals', '4 10');
    });

    it('displays lines with dashes width when props are set', () => {
      initialize({ lineStyle: { dashLength: 5, dashOffset: 8 } });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path[stroke-width="2"]')
        .should('have.attr', 'stroke-dasharray')
        .and('equals', '5 8');
    });

    it('displays only one line with custom style when props are set', () => {
      initialize({
        lineStyle: [
          {
            dashLength: 5,
            dashOffset: 4,
            lineWidth: 1,
            showPoints: true,
            showArea: true,
            metricId: 13534
          }
        ]
      });

      checkGraphWidth();

      cy.contains(':00 AM').should('be.visible');
      cy.get('path.visx-area-closed')
        .should('have.attr', 'stroke-dasharray')
        .and('equals', '5 4');
      cy.get('circle[cx="33.44444444444444"]').should('be.visible');

      cy.makeSnapshot();
    });
  });
});

describe('Lines and bars', () => {
  it('displays lines and bars in the same chart', () => {
    initialize({
      data: dataPingServiceLinesBars
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-7650.368581547736').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays stacked lines and bars in the same chart', () => {
    initialize({
      data: dataPingServiceLinesBarsStacked
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-2-0-6835').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-14920.328518673756').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays mixed lines and bars in the same chart', () => {
    initialize({
      data: dataPingServiceLinesBarsMixed
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-7650.368581547736').should('be.visible');
    cy.findByTestId('stacked-bar-2-0-10').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays lines and bars in the same chart centered in zero', () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBars
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-7650.368581547736').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays stacked lines and bars in the same chart centered in zero', () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBarsStacked
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-2-0-6835').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-14920.328518673756').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays mixed lines and bars in the same chart centered in zero', () => {
    initialize({
      axis: {
        isCenteredZero: true
      },
      data: dataPingServiceLinesBarsMixed
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.findByTestId('stacked-bar-10-0-7650.368581547736').should('be.visible');
    cy.findByTestId('stacked-bar-2-0-10').should('be.visible');

    cy.makeSnapshot();
  });

  it('changes the unit on the left or right scales when a new unit is selected', () => {
    initializeCustomUnits({
      data: dataPingServiceLinesBarsMixed
    });

    checkGraphWidth();

    cy.findAllByTestId('unit-selector').eq(0).parent().click();
    cy.findByLabelText('B').click();

    cy.findAllByTestId('unit-selector').eq(0).should('have.value', 'B');
    cy.contains('8.79 KB').should('be.visible');

    cy.findAllByTestId('unit-selector').eq(1).parent().click();
    cy.findByLabelText('%').click();

    cy.findAllByTestId('unit-selector').eq(1).should('have.value', '%');
    cy.contains('20').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays stacked lines and bars when a line and a bar are customized', () => {
    initialize({
      data: dataPingServiceLinesBarsStacked,
      lineStyle: [
        {
          metricId: 1,
          showArea: false,
          dotOffset: 4,
          lineWidth: 3
        }
      ],
      barStyle: [
        {
          metricId: 10,
          opacity: 0.5,
          radius: 0.3
        }
      ]
    });

    checkGraphWidth();

    cy.get(
      'path[d="M7.501377410468319,273.3424587717121 h56.51239669421488 h1v1 v100.86066622828793 a1,1 0 0 1 -1,1 h-56.51239669421488 a1,1 0 0 1 -1,-1 v-100.86066622828793 v-1h1z"]'
    ).should('be.visible');
    cy.get(
      'path[d="M24.05509641873278,218.3663782225586 h23.404958677685954 a17.553719008264462,17.553719008264462 0 0 1 17.553719008264462,17.553719008264462 v19.86864253262454 v17.553719008264462h-17.553719008264462 h-23.404958677685954 h-17.553719008264462v-17.553719008264462 v-19.86864253262454 a17.553719008264462,17.553719008264462 0 0 1 17.553719008264462,-17.553719008264462z"]'
    ).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays additional lines when props are set', () => {
    initialize({
      data: dataPingServiceLines,
      additionalLines: [
        { color: 'pink', unit: '%', yValue: 3 },
        { color: 'red', unit: 'ms', yValue: 0.15, text: 'some text' }
      ]
    });

    checkGraphWidth();

    cy.get('path[data-metric="1"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');
    cy.get('path[data-metric="3"]').should('be.visible');

    cy.contains('some text').should('be.visible');
    cy.findByTestId('pink-3').should('exist');
    cy.findByTestId('red-0.15').should('exist');

    cy.makeSnapshot();
  });
});