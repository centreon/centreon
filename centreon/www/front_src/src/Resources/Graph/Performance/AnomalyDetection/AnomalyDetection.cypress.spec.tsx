import React from 'react';

import { act, renderHook } from '@testing-library/react-hooks/dom';
import { Provider, useAtom } from 'jotai';
import { BrowserRouter as Router } from 'react-router-dom';

import { platformVersionsAtom } from '../../../../Main/atoms/platformVersionsAtom';
import { detailsAtom } from '../../../Details/detailsAtoms';
import Filter from '../../../Filter';
import { authorizedFilterByModules } from '../../../Filter/Criterias/models';
import { storedFilterAtom } from '../../../Filter/filterAtoms';
import { allFilter } from '../../../Filter/models';
import Resources from '../../../index';
import { enabledAutorefreshAtom } from '../../../Listing/listingAtoms';
import useLoadDetails from '../../../Listing/useLoadResources/useLoadDetails';
import {
  labelAdd,
  labelCancel,
  labelClearFilter,
  labelClose,
  labelCloseEditModal,
  labelDelete,
  labelDisplayEvents,
  labelEditAnomalyDetectionConfirmation,
  labelGraph,
  labelLast31Days,
  labelLast7Days,
  labelLastDay,
  labelMenageEnvelope,
  labelMenageEnvelopeSubTitle,
  labelModalConfirmation,
  labelModalEditAnomalyDetection,
  labelPerformanceGraphAD,
  labelResetToDefaultValue,
  labelSave,
  labelSearchBar,
  labelSlider
} from '../../../translatedLabels';
import ExportablePerformanceGraphWithTimeline from '../ExportableGraphWithTimeline';
import { Method } from '../../../../../../../cypress/support/commands';

import AnomalyDetectionGraphActions from './graph/AnomalyDetectionGraphActions';
import { getDisplayAdditionalLinesCondition } from './graph';

const installedModules = {
  modules: {
    'centreon-anomaly-detection': {
      fix: '0-beta',
      major: '22',
      minor: '10',
      version: '22.10.0-beta.1'
    }
  },
  web: {},
  widgets: []
};

const moduleName = 'centreon-anomaly-detection';

interface Search {
  type: string;
}

const filtersToBeDisplayedInTypeMenu = Object.values(
  authorizedFilterByModules[moduleName]
);

const filtersToBeDisplayedInSearchBar = Object.keys(
  authorizedFilterByModules[moduleName]
);

const searchWords = filtersToBeDisplayedInSearchBar.reduce(
  (prevValue, currentValue: string): Search => {
    const value = prevValue.type;
    const searchKeyWords = {
      type: value ? `${value},${currentValue}` : `${currentValue}`
    };

    return { ...prevValue, ...searchKeyWords };
  },
  { type: '' }
);

const customImageSnapshotProperty = {
  failureThreshold: 0.06
};

describe('Anomaly detection - Filter', () => {
  beforeEach(() => {
    cy.viewport(1200, 750);

    const storedFilter = renderHook(() => useAtom(storedFilterAtom));

    act(() => {
      storedFilter.result.current[1](allFilter);
    });

    cy.mount({
      Component: (
        <Provider initialValues={[[platformVersionsAtom, installedModules]]}>
          <Filter />
        </Provider>
      )
    });
  });

  it('displays the Anomaly detection criteria value when the type criteria chip is clicked and centreon-anomaly-detection is installed', () => {
    cy.displayFilterMenu();
    filtersToBeDisplayedInTypeMenu.forEach((item) =>
      cy.contains(item).should('be.visible')
    );
    cy.clickOutside();
  });

  it('displays the Anomaly detection criteria value in the search bar when the corresponding type criteria is selected', () => {
    cy.displayFilterMenu();

    filtersToBeDisplayedInTypeMenu.forEach((item) => {
      cy.contains(item).should('be.visible').click();
      cy.get('input[type="checkbox"]').should('be.checked');
    });

    cy.get(`[data-testid="${labelSearchBar}"]`)
      .find('input')
      .should('have.value', `type:${searchWords.type} `);
  });

  it('displays the Anomaly detection criteria value on search proposition when user types type: in the search bar', () => {
    cy.get('input[placeholder=Search]').type('type:');
    filtersToBeDisplayedInSearchBar.forEach((item) =>
      cy.contains(item).should('be.visible')
    );
  });
});

describe('Anomaly detection - Graph', () => {
  beforeEach(() => {
    cy.viewport(1200, 750);

    cy.fixture('resources/anomalyDetectionPerformanceGraph.json')
      .as('graphAnomalyDetection')
      .then((data) => {
        cy.interceptAPIRequest({
          alias: 'getGraphDataAnomalyDetection',
          method: Method.GET,
          path: '**/performance?*',
          response: data
        });
      });

    const { result } = renderHook(() => useLoadDetails());

    const reload = (value: boolean): void => {
      if (!value) {
        return;
      }
      result.current.loadDetails();
    };

    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.mount({
        Component: (
          <Provider initialValues={[[detailsAtom, data]]}>
            <Router>
              <ExportablePerformanceGraphWithTimeline
                interactWithGraph
                getDisplayAdditionalLinesCondition={
                  getDisplayAdditionalLinesCondition
                }
                graphHeight={280}
                renderAdditionalGraphAction={
                  <AnomalyDetectionGraphActions
                    details={data}
                    sendReloadGraphPerformance={reload}
                  />
                }
                resource={data}
              />
            </Router>
          </Provider>
        )
      });
    });
  });

  it('displays the wrench icon on graph actions when resource of type anomaly-detection is selected', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).should('be.visible');
  });

  it('displays the Anomaly detection configuration modal when the corresponding button is clicked', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();

    cy.get(`[data-testid="${labelModalEditAnomalyDetection}"]`).should(
      'be.visible'
    );

    cy.contains(labelLastDay).should('be.visible');
    cy.contains(labelLast7Days).should('be.visible');
    cy.contains(labelLast31Days).should('be.visible');
    cy.contains(labelDisplayEvents).should('be.visible');

    cy.fixture('resources/anomalyDetectionPerformanceGraph.json').then(
      (data) => {
        cy.contains(data.global.title).should('be.visible');
        data.metrics.forEach(({ legend }) =>
          cy.contains(legend).should('be.visible')
        );
      }
    );

    cy.contains(labelMenageEnvelope).should('be.visible');
    cy.contains(labelMenageEnvelopeSubTitle).should('be.visible');
    cy.contains(labelResetToDefaultValue).should('be.visible');
    cy.contains(labelCancel).should('be.visible');
    cy.get(`[data-testid="${labelSave}"]`).should('be.disabled');

    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.get(`[data-testid="${labelAdd}"]`)
        .contains(data.sensitivity.maximum_value)
        .should('be.visible');
      cy.get(`[data-testid="${labelDelete}"]`)
        .contains(data.sensitivity.minimum_value)
        .should('be.visible');

      cy.get(`[data-testid="${labelSlider}"]`)
        .contains(data.sensitivity.default_value)
        .should('be.visible');
      cy.contains('Default').should('be.visible');
    });

    cy.get('[role="dialog"]').scrollTo('bottom');
    cy.contains(labelClose).should('be.visible');
    cy.get('[role="dialog"]').scrollTo('top');
  });

  it('displays the threshold when add or minus buttons are clicked on Anomaly detection configuration modal slider', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();
    cy.waitForRequest('@getGraphDataAnomalyDetection');

    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get('input[type="range"]').should('have.value', '3.1');
    cy.matchImageSnapshot(
      'displays the threshold in the modal - add button clicked',
      customImageSnapshotProperty
    );

    cy.get(`[data-testid="${labelCancel}"]`).click();
    cy.get('input[type="range"]').should('have.value', '3');
    cy.matchImageSnapshot(
      'displays the threshold in the modal - cancel button clicked',
      customImageSnapshotProperty
    );

    cy.get(`[data-testid="${labelDelete}"]`).click();
    cy.get('input[type="range"]').should('have.value', '2.9');
    cy.matchImageSnapshot(
      'displays the threshold in the modal - delete button clicked',
      customImageSnapshotProperty
    );
  });

  it('displays the new values of slider when add or minus buttons of slider are clicked', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();

    cy.get(`[data-testid="${labelAdd}"]`).click();

    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.get('.MuiSlider-valueLabelLabel')
        .contains(data.sensitivity.current_value + 0.1)
        .should('be.visible');
    });

    cy.get(`[data-testid="${labelCancel}"]`).click();

    cy.get(`[data-testid="${labelDelete}"]`).click();
    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.get('.MuiSlider-valueLabelLabel')
        .contains(data.sensitivity.current_value - 0.1)
        .should('be.visible');
    });
  });

  it('displays the default value on slider mark when "Use default value" is checked', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();

    cy.contains(labelResetToDefaultValue).click();
    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.get('.MuiSlider-valueLabelLabel')
        .contains(data.sensitivity.default_value)
        .should('be.visible');
    });
  });

  it('displays the modal of confirmation when clicking on save button of slider', () => {
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get(`[data-testid="${labelSave}"]`).click();
    cy.get(`[data-testid="${labelModalConfirmation}"]`).should('be.visible');
    cy.contains(labelEditAnomalyDetectionConfirmation).should('be.visible');
    cy.matchImageSnapshot();
  });
});

const mockResourceListingRequest = (
  fixture = 'resources/resourceListing.json'
): void => {
  cy.fixture(fixture).then((data) => {
    cy.interceptAPIRequest({
      alias: 'getResourceList',
      method: Method.GET,
      path: '**/resources?*',
      response: data
    });
  });
};

describe('Anomaly detection - Global', () => {
  beforeEach(() => {
    cy.viewport(1200, 750);
    cy.fixture('resources/anomalyDetectionPerformanceGraph.json')
      .as('graphAnomalyDetection')
      .then((data) => {
        cy.interceptAPIRequest({
          alias: 'getGraphDataAnomalyDetection',
          method: Method.GET,
          path: '**/performance?*',
          response: data
        });
      });
    cy.fixture('resources/userFilter.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'filter',
        method: Method.GET,
        path: '**/events-view?*',
        response: data
      });
    });
    cy.fixture('resources/anomalyDetectionDetails.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'anomalyDetectionDetails',
        method: Method.GET,
        path: '**/resources/anomaly-detection/1',
        response: data
      });
    });
    cy.interceptAPIRequest({
      alias: 'putSensitivity',
      method: Method.PUT,
      path: '**/sensitivity',
      response: {
        sensitivity: 3.3
      }
    });
    mockResourceListingRequest();

    const storedFilter = renderHook(() => useAtom(storedFilterAtom));

    act(() => {
      storedFilter.result.current[1](allFilter);
    });

    cy.mount({
      Component: (
        <Provider
          initialValues={[
            [platformVersionsAtom, installedModules],
            [enabledAutorefreshAtom, false]
          ]}
        >
          <Router>
            <Resources />
          </Router>
        </Provider>
      )
    });
  });

  it('displays the wrench icon on graph actions when one row of a resource of anomaly-detection is clicked', () => {
    cy.contains('ad').click();
    cy.get('[data-testid="3"]').contains(labelGraph).click();
    cy.waitForRequest('@getGraphDataAnomalyDetection').then(() =>
      cy.matchImageSnapshot()
    );

    cy.get(`[aria-label="Close"]`).click();
  });

  it('displays the Anomaly detection configuration modal when the corresponding button is clicked', () => {
    cy.contains('ad').click();
    cy.get('[data-testid="3"]').click();
    cy.waitForRequest('@getGraphDataAnomalyDetection');
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();
    cy.waitForRequest('@getGraphDataAnomalyDetection');
    cy.matchImageSnapshot();
    cy.get(`[data-testid="${labelCloseEditModal}"]`).click();
    cy.get(`[aria-label="Close"]`).click();
  });

  it('displays the new value of slider when user confirm the changes on Anomaly detection configuration modal ', () => {
    cy.contains('ad').click();
    cy.get('[data-testid="3"]').click();
    cy.waitForRequest('@getGraphDataAnomalyDetection');
    cy.get(`[data-testid="${labelPerformanceGraphAD}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get(`[data-testid="${labelAdd}"]`).click();
    cy.get(`[data-testid="${labelSave}"]`).click();
    cy.get(`[data-testid="${labelModalConfirmation}"]`).should('be.visible');
    cy.contains(labelEditAnomalyDetectionConfirmation).should('be.visible');
    cy.get(`[aria-label="Save"]`).click();

    cy.waitForRequest('@anomalyDetectionDetails');
    cy.get(`[data-testid="${labelCloseEditModal}"]`).click();
  });

  it('displays the Anomaly detection criteria value when the type criteria chip is clicked and centreon-anomaly-detection is installed', () => {
    cy.waitForRequest('@filter');
    cy.waitForRequest('@anomalyDetectionDetails');
    
    cy.get('[aria-label="Filter options"]').click();

    cy.matchImageSnapshot();
  });

  it('displays the Anomaly detection criteria value on search proposition when user types type: in the search bar', () => {
    cy.get('input[placeholder=Search]').type('type:');
    cy.matchImageSnapshot();
    cy.get(`[data-testid="${labelClearFilter}"]`).click();
  });

  it('displays resources of type anomaly-detection', () => {
    mockResourceListingRequest(
      'resources/resourceListingWithAnomalyDetectionType.json'
    );
    cy.waitForRequest('@getResourceList');
    cy.matchImageSnapshot();
  });
});
