import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';
import { object } from 'yup';
import { hasEditPermissionAtom, isEditingAtom } from '../../../atoms';
import {
  labelMaxValue,
  labelMinMustLowerThanMax,
  labelMinValue
} from '../../../translatedLabels';
import { WidgetPropertyProps } from '../../models';
import Boundaries from './Boundaries';
import { boundariesValidationSchema } from './utils';

interface Props extends Pick<WidgetPropertyProps, 'text'> {
  canEdit?: boolean;
}

const initialize = ({ text = undefined, canEdit = true }: Props): void => {
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
              boundaries: undefined
            }
          }}
          validationSchema={object().shape({
            options: object().shape({
              boundaries: boundariesValidationSchema
            })
          })}
          onSubmit={cy.stub()}
        >
          <Boundaries
            isInGroup
            isSingleAutocomplete={false}
            propertyName="boundaries"
            text={text}
            type=""
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Boundaries', () => {
  it('displays the boundaries fields as disabled when the user cannot edit them', () => {
    initialize({ canEdit: false });

    cy.findByLabelText(labelMinValue).should('be.disabled');
    cy.findByLabelText(labelMaxValue).should('be.disabled');

    cy.makeSnapshot();
  });

  it('changes the boundaries when fields are edited', () => {
    initialize({});

    cy.findByLabelText(labelMinValue).clear().type('20');
    cy.findByLabelText(labelMaxValue).clear().type('30');

    cy.findByLabelText(labelMinValue).should('have.value', '20');
    cy.findByLabelText(labelMaxValue).should('have.value', '30');

    cy.makeSnapshot();
  });

  it('displays an error when the max value is lower than the min value', () => {
    initialize({});

    cy.findByLabelText(labelMinValue).clear().type('30');
    cy.findByLabelText(labelMaxValue).clear().type('20');
    cy.findByLabelText(labelMaxValue).blur();

    cy.contains(labelMinMustLowerThanMax).should('be.visible');

    cy.makeSnapshot();
  });
});
