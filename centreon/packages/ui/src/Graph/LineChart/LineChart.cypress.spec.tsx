import { LineChartData } from '../common/models';

import dataLastDay from './mockedData/lastDay.json';
import dataLastDayWithNullValues from './mockedData/lastDayWithNullValues.json';
import dataLastDayWithIncompleteValues from './mockedData/lastDayWithIncompleteValues.json';
import { args as argumentsData } from './helpers/doc';

import WrapperLineChart from '.';

const initialize = (data = dataLastDay): void => {
  cy.mount({
    Component: (
      <WrapperLineChart
        {...argumentsData}
        data={data as unknown as LineChartData}
      />
    )
  });
};

describe('Line chart', () => {
  describe('Tooltip', () => {
    it('displays a tooltip when the graph is hovered', () => {
      initialize();

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
      initialize();

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(452, 26);

      cy.get('[data-metric="querytime"]').should(
        'have.attr',
        'data-highlight',
        'true'
      );
      cy.get('[data-metric="connTime"]').should(
        'have.attr',
        'data-highlight',
        'false'
      );

      cy.makeSnapshot();
    });

    it('does not display the tooltip when null values are hovered', () => {
      initialize(dataLastDayWithNullValues);

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1160, 100);

      cy.get('[data-metric="querytime"]').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the tooltip with defined values whent the graph is hovered', () => {
      initialize(dataLastDayWithIncompleteValues);

      cy.contains('Min: 70.31').should('be.visible');

      cy.findByTestId('graph-interaction-zone').realMouseMove(1150, 100);

      cy.get('[data-metric="querytime"]').should('be.visible');
      cy.get('[data-metric="hitratio"]').should('not.exist');

      cy.makeSnapshot();
    });
  });
});
