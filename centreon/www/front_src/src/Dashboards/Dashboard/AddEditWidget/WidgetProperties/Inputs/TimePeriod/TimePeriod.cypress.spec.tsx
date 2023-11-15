import { Formik } from 'formik';

import {
  labelCustomize,
  labelFrom,
  labelTimePeriod,
  labelTo
} from '../../../../translatedLabels';
import { editProperties } from '../../../../hooks/useCanEditDashboard';

import TimePeriod from './TimePeriod';
import { options } from './useTimePeriod';

const initializeComponent = (): void => {
  cy.stub(editProperties, 'useCanEditProperties').returns({
    canEdit: true,
    canEditField: true
  });
  cy.clock(new Date(2023, 5, 5, 8, 0, 0).getTime());
  cy.viewport('macbook-13');
  cy.mount({
    Component: (
      <Formik
        initialValues={{
          moduleName: 'widget',
          options: {
            timeperiod: {
              end: null,
              start: null
            }
          }
        }}
        onSubmit={cy.stub()}
      >
        <TimePeriod label="" propertyName="timeperiod" />
      </Formik>
    )
  });
};

describe('Time Period', () => {
  beforeEach(() => {
    initializeComponent();
  });

  it('displays the last hour as pre-selected', () => {
    cy.contains(labelTimePeriod).should('be.visible');
    cy.findByTestId(labelTimePeriod).should('have.value', '1');

    cy.makeSnapshot();
  });

  options.slice(1).forEach(({ id, name }) => {
    it(`selects the time period ${name} when the corresponding option is clicked`, () => {
      cy.findByTestId(labelTimePeriod).parent().eq(0).click();

      cy.contains(name).click();

      cy.findByTestId(labelTimePeriod).should('have.value', `${id}`);

      cy.makeSnapshot();
    });
  });

  it('sets the starts and end fields when the customize option is clicked', () => {
    cy.findByTestId(labelTimePeriod).parent().eq(0).click();

    cy.contains(labelCustomize).click();

    cy.get('input').eq(1).should('have.value', '06/05/2023 07:00 AM');
    cy.get('input').eq(2).should('have.value', '06/05/2023 08:00 AM');
  });

  it('customizes the time period when the corresponding option is clicked and the start and end fields are updated', () => {
    cy.findByTestId(labelTimePeriod).parent().eq(0).click();

    cy.contains(labelCustomize).click();

    cy.findByLabelText(labelFrom)
      .find('input')
      .click({ force: true })
      .type('04');
    cy.findByLabelText(labelTo).find('input').click({ force: true }).type('05');

    cy.get('input').eq(1).should('have.value', '04/05/2023 07:00 AM');
    cy.get('input').eq(2).should('have.value', '05/05/2023 08:00 AM');
  });
});
