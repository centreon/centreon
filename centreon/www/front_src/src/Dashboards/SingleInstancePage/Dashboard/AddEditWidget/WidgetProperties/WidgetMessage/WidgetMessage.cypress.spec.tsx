import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { TestQueryProvider } from '@centreon/ui';
import { widgetPropertiesAtom } from '../../atoms';
import WidgetMessage from './WidgetMessage';

const messageExample = {
  label: 'Message to display bellow widget preview',
  icon: '<path fill-rule="evenodd" d="M15.35 8c0 3.377-2.945 6.25-6.75 6.25S1.85 11.377 1.85 8 4.795 1.75 8.6 1.75 15.35 4.623 15.35 8zm1.25 0c0 4.142-3.582 7.5-8 7.5S.6 12.142.6 8C.6 3.858 4.182.5 8.6.5s8 3.358 8 7.5zM9.229 3.101l-.014 7.3-1.25-.002.014-7.3 1.25.002zm.016 9.249a.65.65 0 1 0-1.3 0 .65.65 0 0 0 1.3 0z"/>'
};

interface MessageType {label : string , icon  : string}

const initialize = (message ?: MessageType): void => {
  const store = createStore();

  store.set(widgetPropertiesAtom, { message });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Formik
            initialValues={{
              moduleName: 'widget',
              options: {},
              message
            }}
            onSubmit={cy.stub()}
          >
            <WidgetMessage />
          </Formik>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Widget Message', () => {
  it('displays the message text and icon when the widget contains the "message" property', () => {
    initialize(messageExample);

    cy.contains('Message to display bellow widget preview');

    cy.findByTestId('Message icon').should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display the message text and icon when the widget does not contain the "message" property', () => {
    initialize();

    cy.findByText('Message to display bellow widget preview').should("not.exist");

    cy.findByTestId('Message icon').should('not.exist');

    cy.makeSnapshot();
  });
});
