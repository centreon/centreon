import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';
import { equals } from 'ramda';

import { hasEditPermissionAtom, isEditingAtom } from '../../../atoms';
import { WidgetPropertyProps } from '../../models';

import WidgetTextField from './TextField';

interface Props extends Pick<WidgetPropertyProps, 'text'> {
  canEdit?: boolean;
  label?: string;
}

const initialize = ({
  text = undefined,
  canEdit = true,
  label = 'Text'
}: Props): void => {
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
              test: equals(text?.type, 'number') ? 2 : 'Text'
            }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetTextField
            isInGroup
            label={label}
            propertyName="test"
            text={text}
            type=""
          />
        </Formik>
      </Provider>
    )
  });
};

describe('WidgetTextField', () => {
  describe('Text', () => {
    it('displays the text field', () => {
      initialize({});

      cy.findByLabelText('Text').should('have.value', 'Text');

      cy.makeSnapshot();
    });

    it('displays in compact mode with an autosize and a label when props are set', () => {
      initialize({ text: { autoSize: true, size: 'compact' } });

      cy.findByLabelText('Text').should('have.value', 'Text');

      cy.makeSnapshot();
    });

    it('changes the input when the field is typed', () => {
      initialize({ text: { autoSize: true, size: 'compact' } });

      cy.findByLabelText('Text').clear().type('updated text');

      cy.findByLabelText('Text').should('have.value', 'updated text');

      cy.makeSnapshot();
    });
  });

  describe('Number', () => {
    it('displays the text field', () => {
      initialize({ text: { type: 'number' } });

      cy.findByLabelText('Text').should('have.value', '2');

      cy.makeSnapshot();
    });

    it('displays in compact mode with an autosize and a label when props are set', () => {
      initialize({ text: { autoSize: true, size: 'compact', type: 'number' } });

      cy.findByLabelText('Text').should('have.value', '2');

      cy.makeSnapshot();
    });

    it('changes the input when the field is typed', () => {
      initialize({ text: { autoSize: true, size: 'compact', type: 'number' } });

      cy.findByLabelText('Text').clear().type('34');

      cy.findByLabelText('Text').should('have.value', '34');

      cy.makeSnapshot();
    });

    it('cannot bypass boundaries when props are set and the field is changed', () => {
      initialize({
        text: {
          autoSize: true,
          max: 20,
          min: -2,
          size: 'compact',
          type: 'number'
        }
      });

      cy.findByLabelText('Text').clear().type('34');

      cy.findByLabelText('Text').should('have.value', '20');

      cy.findByLabelText('Text').clear().type('-5');

      cy.findByLabelText('Text').should('have.value', '-2');

      cy.findByLabelText('Text').clear().type('10');

      cy.findByLabelText('Text').should('have.value', '10');

      cy.makeSnapshot();
    });
  });
});
