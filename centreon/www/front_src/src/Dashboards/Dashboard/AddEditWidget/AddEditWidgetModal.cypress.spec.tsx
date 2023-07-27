/* eslint-disable import/no-unresolved */
import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-genericText/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-genericText/properties.json';

import {
  federatedWidgetsAtom,
  federatedWidgetsPropertiesAtom
} from '../../../federatedModules/atoms';
import {
  labelAdd,
  labelDescription,
  labelEdit,
  labelName,
  labelPleaseChooseAWidgetToActivatePreview,
  labelSelectAWidgetType,
  labelWidgetLibrary
} from '../translatedLabels';
import { labelCancel } from '../../translatedLabels';

import { widgetFormInitialDataAtom } from './atoms';

import { AddEditWidgetModal } from '.';

const genericTextValue =
  '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1}],"direction":null,"format":"","indent":0,"type":"root","version":1}}';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    },
    {
      ...widgetGenericTextConfiguration,
      moduleFederationName: 'centreon-widget-genericText/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetGenericTextProperties
  ]);

  return store;
};

const initialFormDataAdd = {
  id: null,
  moduleName: null,
  options: {},
  panelConfiguration: null
};

const initialFormDataEdit = {
  id: `centreon-widget-text_1`,
  moduleName: widgetTextConfiguration.moduleName,
  options: {
    description: 'Description',
    name: 'Widget name'
  },
  panelConfiguration: {
    federatedComponents: ['./text'],
    path: '/widgets/text'
  }
};

describe('AddEditWidgetModal', () => {
  describe('Add widget', () => {
    beforeEach(() => {
      const store = initializeWidgets();

      store.set(widgetFormInitialDataAtom, initialFormDataAdd);

      cy.viewport('macbook-13');

      cy.mount({
        Component: (
          <Provider store={store}>
            <AddEditWidgetModal />
          </Provider>
        )
      });
    });

    it('displays the modal', () => {
      cy.contains(labelSelectAWidgetType).should('be.visible');
      cy.contains(labelPleaseChooseAWidgetToActivatePreview).should(
        'be.visible'
      );
      cy.findByLabelText(labelWidgetLibrary).should('be.visible');
      cy.findByLabelText(labelCancel).should('be.visible');
      cy.findByLabelText(labelAdd).should('be.visible');

      cy.matchImageSnapshot();
    });

    it('enables the add button when a widget is selected and the properties are filled', () => {
      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelAdd).should('be.disabled');

      cy.findByLabelText(labelName).type('Generic input');
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelAdd).should('be.enabled');

      cy.matchImageSnapshot();
    });

    it('keeps the name when a widget is selected, properties are filled and the widget type is changed', () => {
      const widgetName = 'Widget name';

      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).type(widgetName);
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelAdd).should('be.enabled');

      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains('Generic text (example)').click();

      cy.findByLabelText(labelName).should('have.value', widgetName);
      cy.findByLabelText(labelAdd).should('be.enabled');

      cy.matchImageSnapshot();
    });

    it('displays the preview of the generic text widget when the generic text widget type is selected', () => {
      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains(/^Generic text$/).click();

      cy.findAllByLabelText('RichTextEditor').eq(1).type('Hello ');
      cy.findByLabelText('bold').click();
      cy.findAllByLabelText('RichTextEditor').eq(1).type('World');
      cy.findByLabelText('bold').click();
      cy.findAllByLabelText('RichTextEditor').eq(1).type(`
      
      
      Hello!
      https://centreon.com`);

      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello World');
      cy.findAllByLabelText('RichTextEditor').eq(0).contains('Hello!');
      cy.findAllByLabelText('RichTextEditor')
        .eq(0)
        .contains('https://centreon.com');

      cy.matchImageSnapshot();
    });
  });

  describe('Edit widget', () => {
    beforeEach(() => {
      const store = initializeWidgets();

      store.set(widgetFormInitialDataAtom, initialFormDataEdit);

      cy.viewport('macbook-13');

      cy.mount({
        Component: (
          <Provider store={store}>
            <AddEditWidgetModal />
          </Provider>
        )
      });
    });

    it('displays the modal with pre-filled values', () => {
      cy.contains(labelSelectAWidgetType).should('be.visible');

      cy.findByLabelText(labelWidgetLibrary).should(
        'have.value',
        'Generic text (example)'
      );
      cy.findByLabelText(labelName).should('have.value', 'Widget name');
      cy.findByLabelText(labelDescription).should('have.value', 'Description');
      cy.findByLabelText(labelEdit).should('be.disabled');

      cy.matchImageSnapshot();
    });

    it('changes the widget type when another widget is selected', () => {
      const widgetName = 'Edited widget name';
      cy.findByLabelText(labelWidgetLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).clear().type(widgetName);
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelName).should('have.value', widgetName);
      cy.findByLabelText(labelEdit).should('be.enabled');

      cy.matchImageSnapshot();
    });
  });
});
