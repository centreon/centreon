import { labelPreviewRemainsEmpty } from '../../translatedLabels';
import Widget from '.';

const initialize = ({ panelData }): void => {
  cy.mount({
    Component: (
      <Widget
        dashboardId={1}
        globalRefreshInterval={{ interval: null, type: 'global' }}
        hasDescription={false}
        id="1"
        panelData={panelData}
        panelOptions={null}
        refreshCount={0}
        widgetPrefixQuery="widget"
      />
    )
  });
};
describe('Metric capacity planning widget', () => {
  it('displays a no resources message when the widget does not have selected resources', () => {
    initialize({ panelData: { metrics: [], resources: [] } });

    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a no resources message when the widget does not have selected metrics', () => {
    initialize({
      panelData: {
        metrics: [],
        resources: [
          { resources: [{ id: 0, name: 'my host' }], resourceType: 'host' }
        ]
      }
    });

    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display a no resource message when the widget have selected metrics', () => {
    initialize({
      panelData: {
        metrics: [{ id: 0, name: 'metric' }],
        resources: [
          { resources: [{ id: 0, name: 'my host' }], resourceType: 'host' }
        ]
      }
    });

    cy.contains(labelPreviewRemainsEmpty).should('not.exist');

    cy.makeSnapshot();
  });
});
