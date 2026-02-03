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
describe('BA availability widget', () => {
  it('displays a no resources message when the widget does not have selected resources', () => {
    initialize({ panelData: { resources: [] } });

    cy.contains(labelPreviewRemainsEmpty).should('be.visible');

    cy.makeSnapshot();
  });

  it('does not display a no resource message when the widget have selected metrics', () => {
    initialize({
      panelData: {
        resources: [
          {
            resources: [{ id: 0, name: 'ba-1' }],
            resourceType: 'business-activity'
          }
        ]
      }
    });

    cy.contains(labelPreviewRemainsEmpty).should('not.exist');

    cy.makeSnapshot();
  });
});
