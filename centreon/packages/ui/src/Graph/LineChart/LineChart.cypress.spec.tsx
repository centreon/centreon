import { LineChartData } from '../common/models';
import dataLastDay from '../mockedData/lastDay.json';
import dataLastDayWithNullValues from '../mockedData/lastDayWithNullValues.json';
import dataLastDayWithIncompleteValues from '../mockedData/lastDayWithIncompleteValues.json';
import dataCurvesWithSameColor from '../mockedData/curvesWithSameColor.json';

import { args as argumentsData } from './helpers/doc';
import { LineChartProps } from './models';

import WrapperLineChart from '.';

interface Props
  extends Pick<LineChartProps, 'legend' | 'tooltip' | 'axis' | 'lineStyle'> {
  data?: LineChartData;
}

const initialize = ({
  data = dataLastDay,
  tooltip,
  legend,
  axis,
  lineStyle
}: Props): void => {
  cy.mount({
    Component: (
      <WrapperLineChart
        {...argumentsData}
        axis={axis}
        data={data as unknown as LineChartData}
        legend={legend}
        lineStyle={lineStyle}
        tooltip={tooltip}
      />
    )
  });
};

describe('Line chart', () => {
  describe('Tooltip', () => {
    it('displays a tooltip when the graph is hovered', () => {
      initialize({});

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
      cy.contains('75.93%').should('be.visible');
      cy.contains('0.43 s').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays a metric highlighted when the graph is hovered and the metric is the nearest point', () => {
      initialize({});

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should(
        'have.attr',
        'data-highlight',
        'false'
      );
      cy.get('[data-metric="connTime"]').should(
        'have.attr',
        'data-highlight',
        'true'
      );

      cy.makeSnapshot();
    });

    it('does not display the tooltip when null values are hovered', () => {
      initialize({ data: dataLastDayWithNullValues });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1185, 100);

      cy.get('[data-metric="querytime"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the tooltip with defined values whent the graph is hovered', () => {
      initialize({ data: dataLastDayWithIncompleteValues });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1162, 100);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="hitratio"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the tooltip a single metric when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'single', sortOrder: 'name' } });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="connTime"]').should(
        'have.attr',
        'data-highlight',
        'true'
      );
      cy.get('[data-metric="hitratio"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('does not display the tooltip when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'hidden', sortOrder: 'name' } });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('not.exist');
      cy.get('[data-metric="connTime"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('sorts metrics by their value is ascending when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'ascending' } });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="connTime"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('sorts metrics by their value is descending when the corresponding prop is set', () => {
      initialize({ tooltip: { mode: 'all', sortOrder: 'descending' } });

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="connTime"]').should('be.visible');

      cy.makeSnapshot();
    });
  });

  it('displays the curves with different shades when curves have same color', () => {
    initialize({ data: dataCurvesWithSameColor });

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

      cy.contains('9:00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the legend on the left side of the graph when the corresponding prop is set', () => {
      initialize({ legend: { mode: 'grid', placement: 'left' } });

      cy.get('[data-display-side="true"]').should('exist');
      cy.get('[data-as-list="true"]').should('exist');

      cy.contains('9:00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the legend on the right side of the graph as list when the corresponding props are set', () => {
      initialize({ legend: { mode: 'list', placement: 'right' } });

      cy.get('[data-display-side="true"]').should('exist');
      cy.get('[data-as-list="true"]').should('exist');

      cy.contains('9:00 AM').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Axis', () => {
    it('does not display axis borders when the prop is set', () => {
      initialize({ axis: { showBorder: false } });

      cy.contains('9:00 AM').should('be.visible');

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

      cy.contains('9:00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('not.exist');
      cy.get('g[class="visx-group visx-columns"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays only horizontal lines when the prop is set', () => {
      initialize({ axis: { gridLinesType: 'horizontal' } });

      cy.contains('9:00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('be.visible');
      cy.get('g[class="visx-group visx-columns"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays only vertical lines when the prop is set', () => {
      initialize({ axis: { gridLinesType: 'vertical' } });

      cy.contains('9:00 AM').should('be.visible');

      cy.get('g[class="visx-group visx-rows"]').should('not.exist');
      cy.get('g[class="visx-group visx-columns"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('rotates the tick label when the props is set', () => {
      initialize({ axis: { yAxisTickLabelRotation: -35 } });

      cy.contains('9:00 AM').should('be.visible');

      cy.get('text[transform="rotate(-35, -2, 388)"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays as centered to zero when the prop is set', () => {
      initialize({ axis: { isCenteredZero: true } });

      cy.contains('9:00 AM').should('be.visible');

      cy.contains('0.9').should('be.visible');
      cy.contains('-0.9').should('be.visible');

      cy.makeSnapshot();
    });
  });

  describe('Line style', () => {
    it('displays the curve in a natural style when the prop is set', () => {
      initialize({ lineStyle: { curve: 'natural' } });

      cy.contains('9:00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the curve in a step style when the prop is set', () => {
      initialize({ lineStyle: { curve: 'step' } });

      cy.contains('9:00 AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('shows the area when the prop is set', () => {
      initialize({ lineStyle: { showArea: true } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('path[fill="rgba(102, 153, 204, 0.19999999999999996)"]').should(
        'be.visible'
      );

      cy.makeSnapshot();
    });

    it('shows the area with a custom transparency when props are set', () => {
      initialize({ lineStyle: { areaTransparency: 20, showArea: true } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('path[fill="rgba(102, 153, 204, 0.8)"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('shows points when the prop is set', () => {
      initialize({ lineStyle: { showPoints: true } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('circle[cx="4.0625"]').should('be.visible');
      cy.get('circle[cy="163.69430856642046"]').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays lines with a custom line width when the prop is set', () => {
      initialize({ lineStyle: { lineWidth: 6 } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('path[stroke-width="6"]').should('have.length', 3);

      cy.makeSnapshot();
    });

    it('displays lines with dots width when the prop is set', () => {
      initialize({ lineStyle: { dotOffset: 10, lineWidth: 4 } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('path[stroke-width="4"]')
        .should('have.attr', 'stroke-dasharray')
        .and('equals', '4 10');
    });

    it('displays lines with dots width when the prop is set', () => {
      initialize({ lineStyle: { dashLength: 5, dashOffset: 8 } });

      cy.contains('9:00 AM').should('be.visible');
      cy.get('path[stroke-width="2"]')
        .should('have.attr', 'stroke-dasharray')
        .and('equals', '5 8');
    });
  });
});
