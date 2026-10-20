import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ItemComposition } from '.';
import type { Props } from './ItemComposition';

interface InitializeProps extends Partial<Props> {
  deleteButtonHidden?: boolean;
}

interface Callbacks {
  addItem: ReturnType<typeof rs.fn>;
  deleteItem: ReturnType<typeof rs.fn>;
}

const initialize = ({
  deleteButtonHidden,
  ...props
}: InitializeProps): Callbacks => {
  const addItem = rs.fn();
  const deleteItem = rs.fn();

  render(
    <ItemComposition {...props} labelAdd="Add" onAddItem={addItem}>
      <ItemComposition.Item
        deleteButtonHidden={deleteButtonHidden}
        labelDelete="Delete"
        onDeleteItem={deleteItem}
      >
        <div>Item 1</div>
      </ItemComposition.Item>
      <ItemComposition.Item
        deleteButtonHidden={deleteButtonHidden}
        labelDelete="Delete"
        onDeleteItem={deleteItem}
      >
        <div>Item 2</div>
      </ItemComposition.Item>
    </ItemComposition>
  );

  return {
    addItem,
    deleteItem
  };
};

// What every snapshot showed: both items, in order, and the add button
// with its icon.
const expectItemsAndAddButton = (): void => {
  const item1 = screen.getByText('Item 1');
  const item2 = screen.getByText('Item 2');

  expect(item1).toBeVisible();
  expect(item2).toBeVisible();
  expect(
    item1.compareDocumentPosition(item2) & Node.DOCUMENT_POSITION_FOLLOWING
  ).toBeTruthy();
};

const expectAddButtonWithIcon = (): void => {
  const addButton = screen.getByTestId('Add');

  expect(addButton).toBeVisible();
  expect(addButton).toHaveTextContent('Add');
  expect(addButton.querySelector('[data-testid="AddIcon"]')).toBeVisible();
};

describe('ItemComposition', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the component', () => {
    initialize({});

    expect(screen.getByText('Item 1')).toBeVisible();
    expect(screen.getByText('Item 2')).toBeVisible();
    expect(screen.getAllByTestId('Delete')).toHaveLength(2);
    expect(screen.getByTestId('Add')).toBeEnabled();

    expectItemsAndAddButton();
    expectAddButtonWithIcon();
    screen.getAllByTestId('Delete').forEach((deleteButton) => {
      expect(deleteButton).toBeVisible();
    });
    expect(document.querySelector('[data-linked]')).not.toBeInTheDocument();
  });

  it('displays the component with a secondary label', () => {
    initialize({ secondaryLabel: 'Secondary label' });

    expect(screen.getByText('Secondary label')).toBeVisible();

    // The snapshot: the secondary label on the right of the add button.
    expectItemsAndAddButton();
    expectAddButtonWithIcon();
    expect(
      screen.getByText('Secondary label').getBoundingClientRect().left
    ).toBeGreaterThan(screen.getByTestId('Add').getBoundingClientRect().right);
  });

  it('displays add button as hidden when the prop is set to true', () => {
    initialize({ addbuttonDisabled: true });

    expect(screen.getByTestId('Add')).toBeDisabled();

    expectItemsAndAddButton();
    expect(screen.getByTestId('Add')).toBeVisible();
  });

  it('does not display the add button when the prop is set to true', () => {
    initialize({ addButtonHidden: true });

    expect(screen.queryByTestId('Add')).not.toBeInTheDocument();

    expectItemsAndAddButton();
    screen.getAllByTestId('Delete').forEach((deleteButton) => {
      expect(deleteButton).toBeVisible();
    });
  });

  it('does not display the delete button when the prop is set to true', () => {
    initialize({ deleteButtonHidden: true });

    // The delete buttons are kept in the page with `display: none`: none of
    // them is displayed.
    const deleteButtons = screen.queryAllByTestId('Delete');

    expect(deleteButtons).toHaveLength(2);
    deleteButtons.forEach((deleteButton) => {
      expect(deleteButton).not.toBeVisible();
    });

    expectItemsAndAddButton();
    expectAddButtonWithIcon();
  });

  it('calls the add function when the button is clicked', async () => {
    const { addItem } = initialize({});

    await userEvent.click(screen.getByTestId('Add'));

    expect(addItem).toHaveBeenCalledTimes(1);
  });

  it('calls the delete function when the button is clicked', async () => {
    const { deleteItem } = initialize({});

    await userEvent.click(screen.getAllByTestId('Delete')[1]);

    expect(deleteItem).toHaveBeenCalledTimes(1);
  });

  it('displays items as linked when the prop is set to true', () => {
    initialize({ displayItemsAsLinked: true });

    expect(document.querySelector('[data-linked]')).toBeVisible();

    // The snapshot: the link icon on the left of the items.
    expectItemsAndAddButton();
    expect(screen.getByTestId('LinkIcon')).toBeVisible();
    expect(
      (
        document.querySelector('[data-linked]') as HTMLElement
      ).getBoundingClientRect().right
    ).toBeLessThanOrEqual(
      screen.getByText('Item 1').getBoundingClientRect().left
    );
  });
});
