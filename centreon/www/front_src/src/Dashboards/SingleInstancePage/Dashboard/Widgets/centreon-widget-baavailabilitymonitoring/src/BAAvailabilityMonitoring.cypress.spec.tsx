import Widget from '.';
import { labelPreviewRemainsEmpty } from '../../translatedLabels';

const initialize = ({ panelData }): void => {
  cy.mount({
    Component: (
      <Widget
        panelData={panelData}
        dashboardId={1}
        globalRefreshInterval={{ type: 'global', interval: null }}
        hasDescription={false}
        id="1"
        refreshCount={0}
        widgetPrefixQuery="widget"
        panelOptions={null}
      />
    )
  });
};
describe('BA availability monitoring widget', () => {
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
            resources: [{ id: 0, name: 'my ba' }],
            resourceType: 'business-activity'
          }
        ]
      }
    });

    cy.contains(labelPreviewRemainsEmpty).should('not.exist');

    cy.makeSnapshot();
  });
});
