import Zoom from './Zoom';

const Content = (): JSX.Element => (
  <g style={{ transform: 'translate(300px, 150px)' }}>
    <circle fill="blue" r={50} stroke="black" />
  </g>
);

const ContentWithMultipleShapes = (): JSX.Element => {
  return (
    <g>
      <g style={{ transform: 'translate(300px, 150px)' }}>
        <circle fill="blue" r={50} stroke="black" />
      </g>
      <g style={{ transform: 'translate(600px, 500px)' }}>
        <circle fill="green" r={70} />
      </g>
      <g style={{ transform: 'translate(150px, 600px)' }}>
        <circle fill="red" r={70} />
      </g>
    </g>
  );
};
const ContentWithMultipleShapesWithNegativeTranslations = (): JSX.Element => {
  return (
    <g>
      <g style={{ transform: 'translate(-300px, -150px)' }}>
        <circle fill="blue" r={50} stroke="black" />
      </g>
      <g style={{ transform: 'translate(600px, 500px)' }}>
        <circle fill="green" r={70} />
      </g>
      <g style={{ transform: 'translate(150px, 600px)' }}>
        <circle fill="red" r={70} />
      </g>
    </g>
  );
};

interface Props {
  minimapPosition?;
  showMinimap: boolean;
  tenplate?: () => JSX.Element;
}

const initialize = ({
  showMinimap,
  minimapPosition,
  template = Content
}: Props): void => {
  cy.mount({
    Component: (
      <div style={{ height: '400px', width: '100%' }}>
        <Zoom minimapPosition={minimapPosition} showMinimap={showMinimap}>
          {template}
        </Zoom>
      </div>
    )
  });
};

describe('Zoom', () => {
  it('displays the minimap when the prop is set', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('zooms in when the corresponding buttom is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('zoom in').click();
    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.2, 0, 0, 1.2');

    cy.makeSnapshot();
  });

  it('zooms out when the corresponding buttom is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('zoom out').click();

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.8, 0, 0, 0.8');

    cy.makeSnapshot();
  });

  it('zooms out when the content is scrolled up', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: 20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.9, 0, 0, 0.9');

    cy.makeSnapshot();
  });

  it('zooms in when the content is scrolled down', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.makeSnapshot();
  });

  it('clears the zoom when the corresponding button is clicked', () => {
    initialize({ showMinimap: true });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('zoom-content').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.findByTestId('clear').click();

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');

    cy.makeSnapshot();
  });

  it('does not display the minimap when the prop is set', () => {
    initialize({ showMinimap: false });

    cy.get('g[transform="matrix(1, 0, 0, 1, 0, 0)"]');
    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('not.exist');

    cy.makeSnapshot();
  });

  it('zooms in when the minimap is scrolled up', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('minimap-interaction').realMouseWheel({ deltaY: -20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '1.1, 0, 0, 1.1');

    cy.makeSnapshot();
  });

  it('zooms out when the minimap is scrolled down', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');

    cy.findByTestId('minimap-interaction').realMouseWheel({ deltaY: 20 });

    cy.findByTestId('zoom-content')
      .should('have.attr', 'transform')
      .and('include', '0.9, 0, 0, 0.9');

    cy.makeSnapshot();
  });

  it('moves the view when the mouse is hover the content with the corresponding button pressed down', () => {
    initialize({ showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.findByTestId('zoom-container')
      .trigger('mousedown', 400, 200)
      .trigger('mousemove', 600, 200);

    cy.findByTestId('zoom-content').should(
      'have.attr',
      'transform',
      'matrix(1, 0, 0, 1, 0, 0)'
    );

    cy.makeSnapshot();
  });

  it('displays the minimap in the bottom right when the prop to the corresponding value', () => {
    initialize({ minimapPosition: 'bottom-right', showMinimap: true });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.makeSnapshot();
  });

  it('applies a scale down on the minimap when the content is higher than the original height', () => {
    initialize({ showMinimap: true, template: ContentWithMultipleShapes });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.findByTestId('minimap-interaction')
      .parent()
      .find('g')
      .should('have.attr', 'style')
      .and('include', 'transform: scale(0.684211) translate(-85px, -105px);');

    cy.makeSnapshot();
  });

  it('applies a scale down on the minimap when the content has negative translation values', () => {
    initialize({
      showMinimap: true,
      template: ContentWithMultipleShapesWithNegativeTranslations
    });

    cy.get('g[clip-path="url(#zoom-clip-0)"]').should('be.visible');
    cy.get('svg').should('have.attr', 'height', '400');

    cy.findByTestId('minimap-interaction')
      .parent()
      .find('g')
      .should('have.attr', 'style')
      .and('include', 'transform: scale(0.448276) translate(345px, 195px);');

    cy.makeSnapshot();
  });
});
