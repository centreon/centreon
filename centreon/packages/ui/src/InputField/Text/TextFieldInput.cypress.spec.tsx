import TextField, { TextProps } from '.';

const initialize = (props: TextProps): void => {
  cy.mount({
    Component: <TextField {...props} />
  });
};

describe('Textfield', () => {
  it('does not set password as id when the input is a number and the label contains password', () => {
    initialize({
      type: 'number',
      label: 'Keeper for the password',
      dataTestId: 'Keeper password'
    });

    cy.get('[id="Keeperforthe"]').should('be.visible');
    cy.findAllByTestId('Keeper password').should('have.length', 2);
  });
});
