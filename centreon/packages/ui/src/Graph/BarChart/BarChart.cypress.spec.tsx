import { renderHook } from '@testing-library/react';
import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import dataLastWeek from '../mockedData/lastWeek.json';
import dataPingService from '../mockedData/pingService.json';
import dataPingServiceMixedStacked from '../mockedData/pingServiceMixedStacked.json';
import dataPingServiceStacked from '../mockedData/pingServiceStacked.json';

import BarChart, { BarChartProps } from './BarChart';

const defaultStart = new Date(
  dayjs(Date.now()).subtract(24, 'hour').toDate().getTime()
).toISOString();

const defaultEnd = new Date(Date.now()).toISOString();

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
  barStyle
}: Pick<
  BarChartProps,
  'data' | 'legend' | 'axis' | 'barStyle' | 'orientation' | 'tooltip' | 'start'
>): void => {
  cy.adjustViewport();

  cy.mount({
    Component: (
      <div style={{ height: '100%', width: '100%' }}>
        <BarChart
          axis={axis}
          barStyle={barStyle}
          data={data}
          legend={legend}
          orientation={orientation ?? 'horizontal'}
          tooltip={tooltip}
          {...defaultArgs}
        />
      </div>
    )
  });

  cy.viewport('macbook-13');
};

const checkWidth = (orientation): void => {
  if (orientation === 'vertical') {
    cy.get('g[class*="visx-rows"] > line')
      .eq(0)
      .should('have.attr', 'x2')
      .and('equal', '1135');

    return;
  }
  cy.get('g[class*="visx-rows"] > line')
    .eq(0)
    .should('have.attr', 'x2')
    .and('equal', '1170');
};

describe('Bar chart', () => {
  ['horizontal', 'vertical'].forEach((orientation) => {
    it(`displays the bar chart ${orientation}ly`, () => {
      initialize({ orientation });
      const userData = renderHook(() => useAtomValue(userAtom));
      userData.result.current.locale = 'en';

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.08644').should('be.visible');

      cy.makeSnapshot();
    });

    it(`displays the bar chart ${orientation}ly centered in zero`, () => {
      initialize({ axis: { isCenteredZero: true }, orientation });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.08644').should('be.visible');

      cy.makeSnapshot();
    });

    it(`displays the stacked bar chart ${orientation}ly`, () => {
      initialize({ data: dataPingServiceStacked, orientation });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.16196').should('be.visible');

      cy.makeSnapshot();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data`, () => {
      initialize({ data: dataPingServiceMixedStacked, orientation });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.08644').should('be.visible');
    });

    it(`displays the stacked bar chart ${orientation}ly centered in zero`, () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceStacked,
        orientation
      });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.16196').should('be.visible');

      cy.makeSnapshot();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data centered in zero`, () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceMixedStacked,
        orientation
      });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.08644').should('be.visible');
    });

    it(`displays bar chart ${orientation}ly with a custom style`, () => {
      initialize({
        barStyle: { opacity: 0.5, radius: 0.5 },
        data: dataPingServiceMixedStacked,
        orientation
      });

      checkWidth(orientation);
      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      cy.findByTestId('stacked-bar-3-0-0.08644').should('be.visible');
    });
  });

  it('displays a tooltip when a single bar is hovered', () => {
    initialize({
      orientation: 'horizontal'
    });

    checkWidth('horizontal');
    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-10-0-7650.368581547736').realHover();

    cy.contains('06/19/2024').should('be.visible');
    cy.contains('Centreon-Server: Round-Trip Maximum Time').should(
      'be.visible'
    );
    cy.contains('7.47 KB').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display a tooltip when a bar is hovered and a props is set', () => {
    initialize({
      data: dataPingServiceStacked,
      orientation: 'horizontal',
      tooltip: {
        mode: 'hidden',
        sortOrder: 'descending'
      }
    });

    checkWidth('horizontal');
    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-0.05296').realHover();

    cy.contains('06/19/2024').should('not.exist');

    cy.findByTestId('stacked-bar-3-0-0.12340000000000001').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a tooltip when a stacked bar is hovered', () => {
    initialize({
      data: dataPingServiceStacked,
      orientation: 'horizontal',
      tooltip: {
        mode: 'all',
        sortOrder: 'ascending'
      }
    });

    checkWidth('horizontal');
    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-0.05296').realHover();

    cy.contains('06/19/2024').should('be.visible');
    cy.contains('Centreon-Server: Round-Trip Maximum Time').should(
      'be.visible'
    );
    cy.contains('Centreon-Server: Round-Trip Average Time').should(
      'be.visible'
    );
    cy.contains('Centreon-Server: Round-Trip Minimum Time').should(
      'be.visible'
    );
    cy.contains('0.05 ms').should('be.visible');
    cy.contains('0.02 ms').should('be.visible');
    cy.contains('0.11 ms').should('be.visible');

    cy.findByTestId('stacked-bar-3-0-0.16196').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a tooltip with a single metric when a stacked bar is hovered and a prop is set', () => {
    initialize({
      data: dataPingServiceStacked,
      orientation: 'horizontal',
      tooltip: {
        mode: 'single',
        sortOrder: 'descending'
      }
    });

    checkWidth('horizontal');
    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-0.05296').realHover();

    cy.contains('06/19/2024').should('be.visible');
    cy.contains('Centreon-Server: Round-Trip Average Time').should(
      'be.visible'
    );
    cy.contains('0.05 ms').should('be.visible');

    cy.findByTestId('stacked-bar-3-0-0.16196').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the bottom axis correctly when data starts from several days ago', () => {
    initialize({
      data: dataLastWeek,
      orientation: 'horizontal'
    });

    cy.contains('05/31/2023').should('be.visible');
    cy.contains('06/07/2023').should('be.visible');
  });
});
