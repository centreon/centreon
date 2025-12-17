import FluidTypography from '.';

const initialize = ({ width, min, max, pref }): void => {
  cy.viewport(width, 590);

  cy.mount({
    Component: (
      <FluidTypography max={max} min={min} pref={pref} text="Unreachable" />
    )
  });
};

describe('FluidTypography', () => {
  [500, 300, 150].forEach((width) => {
    Object.entries({ max: '80px', min: '30px', pref: 10 }).forEach(
      ([key, value]) => {
        it(`displays the text when the viewport is ${width}px and the ${key} is ${value}`, () => {
          initialize({ [key]: value, width });

          cy.contains('Unreachable').should('be.visible');

          cy.matchImageSnapshot();
        });
      }
    );
  });
});
