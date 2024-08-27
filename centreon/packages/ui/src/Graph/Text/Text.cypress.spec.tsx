import {
  criticalThresholds,
  rangedThresholds,
  successThresholds,
  warningThresholds
} from '../common/testUtils';
import dataLastWeek from '../mockedData/lastWeek.json';

import { Props, Text } from './Text';

const initialize = (
  args: Omit<Props, 'data' | 'labels' | 'baseColor'>
): void => {
  cy.mount({
    Component: (
      <div style={{ height: '100vh', width: '100vw' }}>
        <Text
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

describe('Text', () => {
  it('displays the text as success when corresponding thresholds are set', () => {
    initialize({ thresholds: successThresholds });

    cy.contains('0.41 s').should('have.css', 'color', 'rgb(136, 185, 34)');
    cy.contains('Warning: 0.5 s').should('be.visible');
    cy.contains('Critical: 1.5 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the text as warning when corresponding thresholds are set', () => {
    initialize({ thresholds: warningThresholds });

    cy.contains('0.41 s').should('have.css', 'color', 'rgb(253, 155, 39)');
    cy.contains('Warning: 0.4 s').should('be.visible');
    cy.contains('Critical: 1.5 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the text as critical when corresponding thresholds are set', () => {
    initialize({ thresholds: criticalThresholds });

    cy.contains('0.41 s').should('have.css', 'color', 'rgb(255, 74, 74)');
    cy.contains('Warning: 0.2 s').should('be.visible');
    cy.contains('Critical: 0.3 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays ranged thresholds', () => {
    initialize({ thresholds: rangedThresholds });

    cy.contains('Warning: 0.13 s - 0.5 s').should('be.visible');
    cy.contains('Critical: 0.55 s - 0.65 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the value as raw when the prop is set', () => {
    initialize({ displayAsRaw: true, thresholds: successThresholds });

    cy.contains('0.40663333333 s').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display the text', () => {
    initialize({ data: undefined, thresholds: successThresholds });

    cy.contains('0.41 s').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays text with default values when the data is empty', () => {
    initialize({
      data: {
        global: {},
        metrics: [],
        times: []
      },
      thresholds: successThresholds
    });

    cy.contains('0').should('be.visible');

    cy.makeSnapshot();
  });
});
