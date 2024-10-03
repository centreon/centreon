import {
  criticalThresholds,
  rangedThresholds,
  successThresholds,
  warningThresholds
} from '../common/testUtils';
import dataLastWeek from '../mockedData/lastWeek.json';

import SingleBar from './SingleBar';
import { SingleBarProps } from './models';

const initialize = (
  args: Omit<SingleBarProps, 'data' | 'labels' | 'baseColor'>
): void => {
  cy.mount({
    Component: (
      <div style={{ height: '100vh', width: '100vw' }}>
        <SingleBar
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

describe('Single bar', () => {
  it('displays the single bar as success when corresponding thresholds are set', () => {
    initialize({ thresholds: successThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(136, 185, 34)');
    cy.findByTestId('warning-line-0.5').should('be.visible');
    cy.findByTestId('warning-line-0.5-tooltip').should('be.visible');
    cy.findByTestId('critical-line-1.5').should('be.visible');
    cy.findByTestId('critical-line-1.5-tooltip').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the single bar as warning when corresponding thresholds are set', () => {
    initialize({ thresholds: warningThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(253, 155, 39)');
    cy.findByTestId('warning-line-0.4').should('be.visible');
    cy.findByTestId('warning-line-0.4-tooltip').should('be.visible');
    cy.findByTestId('critical-line-1.5').should('be.visible');
    cy.findByTestId('critical-line-1.5-tooltip').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the single bar as critical when corresponding thresholds are set', () => {
    initialize({ thresholds: criticalThresholds });

    cy.contains('0.41 s').should('have.css', 'fill', 'rgb(255, 74, 74)');
    cy.findByTestId('warning-line-0.2').should('be.visible');
    cy.findByTestId('warning-line-0.2-tooltip').should('be.visible');
    cy.findByTestId('critical-line-0.3').should('be.visible');
    cy.findByTestId('critical-line-0.3-tooltip').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays ranged thresholds', () => {
    initialize({ thresholds: rangedThresholds });

    cy.findByTestId('warning-line-0.13').should('be.visible');
    cy.findByTestId('warning-line-0.13-tooltip').should('be.visible');
    cy.findByTestId('warning-line-0.5').should('be.visible');
    cy.findByTestId('warning-line-0.5-tooltip').should('be.visible');
    cy.findByTestId('critical-line-0.55').should('be.visible');
    cy.findByTestId('critical-line-0.55-tooltip').should('be.visible');
    cy.findByTestId('critical-line-0.65').should('be.visible');
    cy.findByTestId('critical-line-0.65-tooltip').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the threshold tooltip when a threshold is hovered', () => {
    initialize({ thresholds: successThresholds });

    cy.findByTestId('warning-line-0.5-tooltip').trigger('mouseover');

    cy.contains('Warning').should('be.visible');

    cy.findByTestId('critical-line-1.5-tooltip').trigger('mouseover');

    cy.contains('Critical').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays single bar as small when the props is set', () => {
    initialize({ size: 'small', thresholds: successThresholds });

    cy.findByTestId('warning-line-0.5-tooltip').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the value as raw when the prop is set', () => {
    initialize({ displayAsRaw: true, thresholds: successThresholds });

    cy.contains('0.40663333333 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display the value when the prop is set', () => {
    initialize({ showLabels: false, thresholds: successThresholds });

    cy.contains('0.41 s').should('not.exist');

    cy.makeSnapshot();
  });
});
