/* eslint-disable import/no-unresolved */
import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';

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
  labelWidgetsLibrary
} from '../translatedLabels';
import { labelCancel } from '../../translatedLabels';

import { widgetFormInitialDataAtom } from './atoms';

import { AddEditWidgetModal } from '.';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties
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
      cy.findByLabelText(labelWidgetsLibrary).should('be.visible');
      cy.findByLabelText(labelCancel).should('be.visible');
      cy.findByLabelText(labelAdd).should('be.visible');

      cy.matchImageSnapshot();
    });

    it('enables the add button when a widget is selected and the properties are filled', () => {
      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelAdd).should('be.disabled');

      cy.findByLabelText(labelName).type('Generic input');
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelAdd).should('be.enabled');

      cy.matchImageSnapshot();
    });

    it('keeps the name when a widget is selected, properties are filled and the widget type is changed', () => {
      const widgetName = 'Widget name';

      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).type(widgetName);
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelAdd).should('be.enabled');

      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic text (example)').click();

      cy.findByLabelText(labelName).should('have.value', widgetName);
      cy.findByLabelText(labelAdd).should('be.enabled');

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

      cy.findByLabelText(labelWidgetsLibrary).should(
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
      cy.findByLabelText(labelWidgetsLibrary).click();
      cy.contains('Generic input (example)').click();

      cy.findByLabelText(labelName).clear().type(widgetName);
      cy.findByLabelText('Generic text').type('Text');

      cy.findByLabelText(labelName).should('have.value', widgetName);
      cy.findByLabelText(labelEdit).should('be.enabled');

      cy.matchImageSnapshot();
    });
  });
});
