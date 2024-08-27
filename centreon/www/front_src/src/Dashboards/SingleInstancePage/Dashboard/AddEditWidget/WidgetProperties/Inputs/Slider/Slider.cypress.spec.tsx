import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import Slider from './Slider';

const initialize = ({ isInGroup = false, canEdit = true, slider }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              test: 3
            }
          }}
          onSubmit={cy.stub()}
        >
          <Slider
            isInGroup={isInGroup}
            label="Slider"
            propertyName="test"
            slider={slider}
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Slider', () => {
  it('displays slider with default value', () => {
    initialize({});

    cy.contains('Slider').should('be.visible');
    cy.get('input').should('have.value', '3');

    cy.makeSnapshot();
  });

  it('changes the slider value when the slider thumb is moved', () => {
    initialize({});

    cy.contains('Slider').should('be.visible');
    cy.get('input').should('have.value', '3');
    cy.findByTestId('slider-test')
      .get('.MuiSlider-thumb')
      .should('have.css', 'left')
      .and('equal', '9px');

    cy.findByTestId('slider-test').get('.MuiSlider-thumb').click();
    cy.realPress(['ArrowRight', 'ArrowRight', 'ArrowRight']);

    cy.get('input').should('have.value', '6');
    cy.findByTestId('slider-test')
      .get('.MuiSlider-thumb')
      .should('have.css', 'left')
      .and('equal', '18px');

    cy.makeSnapshot();
  });

  it('changes the slider value when the input is changed', () => {
    initialize({});

    cy.contains('Slider').should('be.visible');
    cy.get('input').should('have.value', '3');
    cy.findByTestId('slider-test')
      .get('.MuiSlider-thumb')
      .should('have.css', 'left')
      .and('equal', '9px');

    cy.findByLabelText('slider-test-input').type('{selectall}30');

    cy.get('input').should('have.value', '30');
    cy.findByTestId('slider-test')
      .get('.MuiSlider-thumb')
      .should('have.css', 'left')
      .and('equal', '90px');

    cy.makeSnapshot();
  });

  it('cannot select value outside of boundaries when props are set', () => {
    initialize({
      slider: {
        max: 30,
        min: -30
      }
    });

    cy.get('input').should('have.value', '3');

    cy.findByLabelText('slider-test-input').type('{selectall}31');

    cy.get('input').should('have.value', '30');

    cy.findByLabelText('slider-test-input').type('{selectall}-31');

    cy.get('input').should('have.value', '-30');

    cy.makeSnapshot();
  });

  it('displays the unit when prop is set', () => {
    initialize({
      slider: {
        max: 30,
        min: -30,
        unit: '•••'
      }
    });

    cy.contains('•••').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Slider', () => {
  it('displays the slider as disabled', () => {
    initialize({ canEdit: false });

    cy.findByLabelText('slider-test-input').should('be.disabled');
    cy.findByTestId('slider-test').get('input').should('be.disabled');

    cy.makeSnapshot();
  });
});
