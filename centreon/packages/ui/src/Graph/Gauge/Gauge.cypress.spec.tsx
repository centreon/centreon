import {
  criticalThresholds,
  rangedThresholds,
  successThresholds,
  warningThresholds
} from '../common/testUtils';
import dataLastWeek from '../mockedData/lastWeek.json';

import { Gauge, Props } from './Gauge';

const initialize = (
  args: Omit<Props, 'data' | 'labels' | 'baseColor'>
): void => {
  cy.mount({
    Component: (
      <div style={{ height: '100vh', width: '100vw' }}>
        <Gauge
          baseColor="#000"
          data={dataLastWeek}
          labels={{
            critical: 'Critical',
            warning: 'Warning'
          }}
          {...args}
        />
      </div>
    )
  });
};

describe('Gauge', () => {
  it('does not display the gauge when there is no data', () => {
    initialize({ data: undefined, thresholds: successThresholds });

    cy.contains('0.41 s').should('not.exist');
  });

  it('displays the gauge as success when corresponding thresholds are set', () => {
    initialize({ thresholds: successThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(136, 185, 34)');
    cy.findByTestId('1.1500000000000001-arc').should('be.visible');
    cy.findByTestId('0.15000000000000013-arc').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the gauge as warning when corresponding thresholds are set', () => {
    initialize({ thresholds: warningThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(253, 155, 39)');
    cy.findByTestId('1.25-arc').should('be.visible');
    cy.findByTestId('0.15000000000000013-arc').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the gauge as critical when corresponding thresholds are set', () => {
    initialize({ thresholds: criticalThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(255, 74, 74)');
    cy.findByTestId('0.6399999999999999-arc').should('be.visible');
    cy.findByTestId('0.54-arc').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays ranged thresholds', () => {
    initialize({ thresholds: rangedThresholds });

    cy.findByTestId('0.37-arc').should('be.visible');
    cy.findByTestId('0.09999999999999998-arc').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the threshold tooltip when a threshold is hovered', () => {
    initialize({ thresholds: successThresholds });

    cy.findByTestId('1.1500000000000001-arc').trigger('mouseover', {
      force: true
    });

    cy.contains('Warning').should('be.visible');

    cy.findByTestId('0.15000000000000013-arc').trigger('mouseover', {
      force: true
    });

    cy.contains('Critical').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the value as raw when the prop is set', () => {
    initialize({ displayAsRaw: true, thresholds: successThresholds });

    cy.contains('0.40663333333 s').should('be.visible');

    cy.makeSnapshot();
  });
});
