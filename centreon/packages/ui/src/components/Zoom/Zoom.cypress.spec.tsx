import Zoom from './Zoom';

const content = (
  <g style={{ transform: 'translate(300px, 150px)' }}>
    <circle fill="blue" r={50} stroke="black" />
  </g>
);

const labels = {
  clear: 'Clear',
  zoomIn: '+',
  zoomOut: '-'
};

const initialize = ({ showMinimap }): void => {
  cy.mount({
    Component: (
      <div style={{ height: '400px', width: '100%' }}>
        <Zoom labels={labels} showMinimap={showMinimap}>
          {content}
        </Zoom>
      </div>
    )
  });
};

describe('Zoom', () => {
  it('displays the minimap when the prop is set', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('zooms in when the corresponding buttom is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.contains('+').click();
    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.2, 0, 0, 1.2');

    cy.makeSnapshot();
  });

  it('zooms out when the corresponding buttom is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.contains('-').click();

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.8, 0, 0, 0.8');

    cy.makeSnapshot();
  });

  it('zooms out when the content is scrolled', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: 20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.9, 0, 0, 0.9');

    cy.makeSnapshot();
  });

  it('zooms in when the content is scrolled', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.makeSnapshot();
  });

  it('clears the zoom when the corresponding button is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.contains('Clear').click();

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');

    cy.makeSnapshot();
  });

  it('highlights the minimap when the mouse is over the minimap', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.get('g[clip-path="url(#zoom-clip)"]').realHover();

    cy.makeSnapshot();
  });

  it('does not display the minimap the prop is set', () => {
    initialize({ showMinimap: false });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('not.exist');

    cy.makeSnapshot();
  });
});
