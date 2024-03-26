import Zoom from './Zoom';

const content = (
  <g style={{ transform: 'translate(300px, 150px)' }}>
    <circle fill="blue" r={50} stroke="black" />
  </g>
);

const labels = {
  clear: 'Clear'
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

    cy.findByTestId('zoom in').click();
    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.2, 0, 0, 1.2');

    cy.makeSnapshot();
  });

  it('zooms out when the corresponding buttom is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('zoom out').click();

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.8, 0, 0, 0.8');

    cy.makeSnapshot();
  });

  it('zooms out when the content is scrolled up', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: 20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.9, 0, 0, 0.9');

    cy.makeSnapshot();
  });

  it('zooms in when the content is scrolled down', () => {
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

  it('does not display the minimap when the prop is set', () => {
    initialize({ showMinimap: false });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip)"]').should('not.exist');

    cy.makeSnapshot();
  });

  it('zooms in when the minimap is scrolled up', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('minimap-interaction').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.makeSnapshot();
  });

  it('zooms out when the minimap is scrolled down', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');

    cy.findByTestId('minimap-interaction').realMouseWheel({ deltaY: 20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.9, 0, 0, 0.9');

    cy.makeSnapshot();
  });

  it('moves the view when the minimap is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.findByTestId('minimap-interaction').click(20, 20);

    cy.findByTestId('zoom-content').should(
      'have.attr',
      'transform',
      'matrix(1, 0, 0, 1, -100, -100)'
    );

    cy.makeSnapshot();
  });

  it('moves the view when the mouse is hover the minimap with the correspondiong button pressed down', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.findByTestId('minimap-interaction')
      .realMouseDown()
      .realMouseMove(40, 40);

    cy.findByTestId('zoom-content').should(
      'have.attr',
      'transform',
      'matrix(1, 0, 0, 1, -640, -200)'
    );

    cy.makeSnapshot();
  });
});
