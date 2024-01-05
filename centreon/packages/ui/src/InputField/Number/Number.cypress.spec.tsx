import NumberField, { NumberProps } from './Number';

const initialize = (props: NumberProps): void => {
  cy.mount({
    Component: <NumberField {...props} />
  });
};

describe('Number field', () => {
  it('displays the placeholder when the field has no default or fallback values', () => {
    initialize({ dataTestId: 'test', onChange: cy.stub() });

    cy.get('input').should('have.value', '');
    cy.get('input').should('have.attr', 'placeholder', '0');

    cy.makeSnapshot();
  });

  it('displays the fallback value as placeholder when the field as no default value', () => {
    initialize({ dataTestId: 'test', fallbackValue: 25, onChange: cy.stub() });

    cy.get('input').should('have.value', '');
    cy.get('input').should('have.attr', 'placeholder', '25');

    cy.makeSnapshot();
  });

  it('displays the default value as placeholder when the field as default value', () => {
    initialize({ dataTestId: 'test', defaultValue: 25, onChange: cy.stub() });

    cy.get('input').should('have.value', '25');

    cy.makeSnapshot();
  });

  it('displays the fallback value when the field is cleared out', () => {
    initialize({
      dataTestId: 'test',
      defaultValue: 25,
      fallbackValue: 10,
      onChange: cy.stub()
    });

    cy.get('input').should('have.value', '25');
    cy.get('input').clear();
    cy.get('input').should('have.value', '');
    cy.get('input').should('have.attr', 'placeholder', '10');

    cy.makeSnapshot();
  });

  it('displays the field using auto size', () => {
    initialize({
      autoSize: true,
      dataTestId: 'test',
      defaultValue: 25,
      onChange: cy.stub()
    });

    cy.get('input').should('have.value', '25');

    cy.get('input').type('0');

    cy.makeSnapshot();
  });
});
