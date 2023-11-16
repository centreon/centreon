import { labelMinute, labelMinutes } from '../translatedLabels';

import TimeInput, { TimeInputProps } from './TimeInput';

const initialize = (props: Omit<TimeInputProps, 'onChange'>): unknown => {
  const mockChange = cy.stub();

  cy.mount({
    Component: <TimeInput {...props} onChange={mockChange} />
  });

  return mockChange;
};

describe('Time input', () => {
  it('updates the time value to 2040000 milliseconds value when "34" is typed in the input', () => {
    const mockChange = initialize({
      inputLabel: 'input',
      labels: { plural: labelMinutes, singular: labelMinute },
      name: 'input',
      timeValue: 0,
      unit: 'minutes'
    });

    cy.findByLabelText(`input ${labelMinute}`).click();
    cy.findByText('34').click();

    cy.wrap(mockChange).should('have.been.calledWith', 2040000);
  });

  it('does not display options below the configured min value except 0', () => {
    initialize({
      inputLabel: 'input',
      labels: { plural: labelMinutes, singular: labelMinute },
      minOption: 2,
      name: 'input',
      timeValue: 0,
      unit: 'minutes'
    });

    cy.findByLabelText(`input ${labelMinute}`).click();
    cy.findAllByText('0').first().should('exist');
    cy.findByText('1').should('not.exist');
    cy.findByText('2').should('exist');
  });

  it('displays the label text in singular when the input value is 0', () => {
    initialize({
      inputLabel: 'input',
      labels: { plural: labelMinutes, singular: labelMinute },
      name: 'input',
      timeValue: 0,
      unit: 'minutes'
    });

    cy.findByText(labelMinute).should('be.visible');
  });

  it('displays the label text in singular when the input value is 1', () => {
    initialize({
      inputLabel: 'input',
      labels: { plural: labelMinutes, singular: labelMinute },
      name: 'input',
      timeValue: 60000,
      unit: 'minutes'
    });

    cy.findByText(labelMinute).should('be.visible');
  });

  it('displays the label text in plural when the input value is 2', () => {
    initialize({
      inputLabel: 'input',
      labels: { plural: labelMinutes, singular: labelMinute },
      name: 'input',
      timeValue: 120000,
      unit: 'minutes'
    });

    cy.findByText(labelMinutes).should('be.visible');
  });
});
