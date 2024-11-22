import { Provider, atom, createStore, useSetAtom } from 'jotai';

import { Button } from '@mui/material';

import { ConfirmationModal, ConfirmationModalProps } from './ConfirmationModal';

const testAtom = atom<string | null>(null);

const buttonLabel = 'Click to open modal';

const TestComponent = (args: ConfirmationModalProps<string>): JSX.Element => {
  const setAtom = useSetAtom(testAtom);

  return (
    <>
      <Button onClick={() => setAtom('John')}>{buttonLabel}</Button>
      <ConfirmationModal<string> {...args} />
    </>
  );
};

const staticLabels = {
  cancel: 'Cancel',
  confirm: 'Confirm',
  description: 'Description',
  title: 'Title'
};

const dynamicLabels = {
  cancel: 'Cancel',
  confirm: 'Confirm',
  description: (data) => `Description ${data}`,
  title: (data) => `Hello ${data}`
};

const initialize = (
  props: Pick<
    ConfirmationModalProps<string>,
    'labels' | 'disabled' | 'hasCloseButton' | 'isDanger'
  >
): { cancel; confirm } => {
  const store = createStore();

  const cancel = cy.stub();
  const confirm = cy.stub();

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestComponent
          {...props}
          atom={testAtom}
          onCancel={cancel}
          onConfirm={confirm}
        />
      </Provider>
    )
  });

  return {
    cancel,
    confirm
  };
};

describe('Confirmation modal', () => {
  it('displays the modal with static labels', () => {
    initialize({ labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Title').should('be.visible');
    cy.contains('Description').should('be.visible');
    cy.findByLabelText('close').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the modal with dynamic labels', () => {
    initialize({ labels: dynamicLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Hello John').should('be.visible');
    cy.contains('Description John').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the confirm button as disabled when a prop is set', () => {
    initialize({ disabled: true, labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Confirm').should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays the confirm button as danger when a prop is set', () => {
    initialize({ isDanger: true, labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Confirm').should('have.attr', 'data-is-danger', 'true');

    cy.makeSnapshot();
  });

  it('displays the modal without the close button when a prop is set', () => {
    initialize({ hasCloseButton: false, labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.findByLabelText('close').should('not.exist');

    cy.makeSnapshot();
  });

  it('closes the modal when the close button is clicked', () => {
    initialize({ labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Title').should('be.visible');

    cy.findByLabelText('close').click();

    cy.contains('Title').should('not.exist');

    cy.makeSnapshot();
  });

  it('closes the modal when the cancel button is clicked', () => {
    const { cancel } = initialize({ labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Title').should('be.visible');

    cy.contains('Cancel')
      .click()
      .then(() => {
        expect(cancel).to.be.calledWith('John');
      });

    cy.contains('Title').should('not.exist');

    cy.makeSnapshot();
  });
  it('closes the modal when the confirm button is clicked', () => {
    const { confirm } = initialize({ labels: staticLabels });

    cy.contains(buttonLabel).click();

    cy.contains('Title').should('be.visible');

    cy.contains('Confirm')
      .click()
      .then(() => {
        expect(confirm).to.be.calledWith('John');
      });

    cy.contains('Title').should('not.exist');

    cy.makeSnapshot();
  });
});
