import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import {
  labelCustomize,
  labelFrom,
  labelTimePeriod,
  labelTo
} from '../../../../translatedLabels';

import TimePeriod from './TimePeriod';
import { options } from './useTimePeriod';

const openCalendar = (label): void => {
  cy.findByLabelText(label).then(($input) => {
    if ($input.attr('readonly')) {
      cy.wrap($input).click();
    } else {
      cy.findByLabelText(label).findByRole('button').click({ force: true });
    }
  });
};

const initializeComponent = (canEdit = true): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.clock(new Date(2023, 5, 5, 8, 0, 0).getTime());
  cy.viewport('macbook-13');
  cy.mount({
    Component: (
      <Provider store={store}>
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
      </Provider>
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

    openCalendar(labelFrom);

    cy.findByRole('option', { name: '10 hours' }).click();

    openCalendar(labelTo);

    cy.findAllByRole('option', { name: '5 hours' }).eq(1).click();

    cy.get('input').eq(1).should('have.value', '06/05/2023 10:00 AM');
    cy.get('input').eq(2).should('have.value', '06/05/2023 05:00 AM');
  });
});

describe('Time period disabled', () => {
  beforeEach(() => initializeComponent(false));

  it('displays the time period field as disabled when the user is not allowed to edit field', () => {
    cy.findByTestId(labelTimePeriod).should('be.disabled');

    cy.makeSnapshot();
  });
});
