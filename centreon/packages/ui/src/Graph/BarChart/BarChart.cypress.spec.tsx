import { renderHook } from '@testing-library/react';
import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { userAtom } from '@centreon/ui-context';

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
  'data' | 'legend' | 'axis' | 'barStyle' | 'orientation' | 'tooltip'
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

describe('Bar chart', () => {
  ['horizontal', 'vertical'].forEach((orientation) => {
    it(`displays the bar chart ${orientation}ly`, () => {
      initialize({ orientation });
      const userData = renderHook(() => useAtomValue(userAtom));
      userData.result.current.locale = 'en';

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('single-bar-3-2-0.08644')
          .should('have.attr', 'height')
          .should('equals', '295');
      } else {
        cy.findByTestId('single-bar-3-2-0.08644')
          .should('have.attr', 'width')
          .should('equals', '863');
      }

      cy.makeSnapshot();
    });

    it(`displays the bar chart ${orientation}ly centered in zero`, () => {
      initialize({ axis: { isCenteredZero: true }, orientation });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('single-bar-3-2-0.08644')
          .should('have.attr', 'height')
          .should('equals', '171');
      } else {
        cy.findByTestId('single-bar-3-2-0.08644')
          .should('have.attr', 'width')
          .should('equals', '432');
      }

      cy.makeSnapshot();
    });

    it(`displays the stacked bar chart ${orientation}ly`, () => {
      initialize({ data: dataPingServiceStacked, orientation });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
          .should('have.attr', 'height')
          .should('equals', '187');
      } else {
        cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
          .should('have.attr', 'width')
          .should('equals', '546');
      }

      cy.makeSnapshot();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data`, () => {
      initialize({ data: dataPingServiceMixedStacked, orientation });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'height')
          .should('equals', '265');
      } else {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'width')
          .should('equals', '773');
      }

      cy.makeSnapshot();
    });

    it(`displays the stacked bar chart ${orientation}ly centered in zero`, () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceStacked,
        orientation
      });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
          .should('have.attr', 'height')
          .should('equals', '94');
      } else {
        cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
          .should('have.attr', 'width')
          .should('equals', '273');
      }

      cy.makeSnapshot();
    });

    it(`displays bar chart ${orientation}ly with a mix of stacked and non-stacked data centered in zero`, () => {
      initialize({
        axis: { isCenteredZero: true },
        data: dataPingServiceMixedStacked,
        orientation
      });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'height')
          .should('equals', '133');
      } else {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'width')
          .should('equals', '387');
      }
    });

    it(`displays bar chart ${orientation}ly with a custom style`, () => {
      initialize({
        barStyle: { opacity: 0.5, radius: 0.5 },
        data: dataPingServiceMixedStacked,
        orientation
      });

      cy.contains('0 ms').should('be.visible');
      cy.contains('20').should('be.visible');
      cy.contains(':40 AM').should('be.visible');

      if (equals(orientation, 'horizontal')) {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'height')
          .should('equals', '265');
      } else {
        cy.findByTestId('stacked-bar-3-0-0.08644')
          .should('have.attr', 'width')
          .should('equals', '773');
      }
    });
  });

  it('displays a tooltip when a single bar is hovered', () => {
    initialize({
      orientation: 'horizontal'
    });

    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('single-bar-3-2-0.11372').realHover();

    cy.contains('06/19/2024').should('be.visible');
    cy.contains('Centreon-Server: Round-Trip Maximum Time').should(
      'be.visible'
    );
    cy.contains('0.11 ms').should('be.visible');

    cy.findByTestId('single-bar-3-2-0.08644')
      .should('have.attr', 'height')
      .should('equals', '295');

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

    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-0.05296').realHover();

    cy.contains('06/19/2024').should('not.exist');

    cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
      .should('have.attr', 'height')
      .should('equals', '187');

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

    cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
      .should('have.attr', 'height')
      .should('equals', '187');

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

    cy.contains('0 ms').should('be.visible');
    cy.contains('20').should('be.visible');
    cy.contains(':40 AM').should('be.visible');

    cy.findByTestId('stacked-bar-1-0-0.05296').realHover();

    cy.contains('06/19/2024').should('be.visible');
    cy.contains('Centreon-Server: Round-Trip Average Time').should(
      'be.visible'
    );
    cy.contains('0.05 ms').should('be.visible');

    cy.findByTestId('stacked-bar-3-0-0.12340000000000001')
      .should('have.attr', 'height')
      .should('equals', '187');

    cy.makeSnapshot();
  });
});
