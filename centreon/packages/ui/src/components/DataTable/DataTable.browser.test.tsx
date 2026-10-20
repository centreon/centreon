import Delete from '@mui/icons-material/Delete';
import { Box } from '@mui/material';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, within } from '@testing-library/react';

import { IconButton } from '../..';
import { ColumnType, type RowRecord } from '../../Listing/models';
import { DataTable } from '.';

interface Entity {
  description: string;
  id: number;
  title: string;
}

const data: Array<Entity> = Array(5)
  .fill(0)
  .map((_, idx) => ({
    description: `Description ${idx}`,
    id: idx,
    title: `Entity ${idx}`
  }));

const CardActions = (): JSX.Element => {
  return (
    <IconButton ariaLabel="Delete" onClick={rs.fn()} title="Delete">
      <Delete fontSize="small" />
    </IconButton>
  );
};

interface InitializeDataTableGridProps {
  hasActions: boolean;
  hasCardAction: boolean;
}

const initializeDataTableGrid = ({
  hasActions,
  hasCardAction
}: InitializeDataTableGridProps): void => {
  // The width Cypress set with `cy.viewport(1200, 590)`.
  render(
    <div style={{ width: 1200 }}>
      <DataTable variant="grid">
        {data.map(({ title, description }) => (
          <DataTable.Item
            Actions={<CardActions />}
            description={description}
            hasActions={hasActions}
            hasCardAction={hasCardAction}
            key={title}
            title={title}
          />
        ))}
      </DataTable>
    </div>
  );
};

const initializeDataTableEmpty = (canCreate = false): void => {
  render(
    <DataTable isEmpty variant="grid">
      <DataTable.EmptyState
        canCreate={canCreate}
        labels={{
          actions: {
            create: 'Create'
          },
          description: 'Description',
          title: 'Welcome'
        }}
        onCreate={rs.fn()}
      />
    </DataTable>
  );
};

const initializeDataTableListing = (): void => {
  render(
    <Box sx={{ height: '100vh' }}>
      <DataTable variant="listing">
        <DataTable.Listing
          columns={[
            {
              getFormattedString: (row: RowRecord): string =>
                (row as unknown as Entity).title,
              id: 'title',
              label: 'Title',
              type: ColumnType.string
            },
            {
              getFormattedString: (row: RowRecord): string =>
                (row as unknown as Entity).description,
              id: 'description',
              label: 'Description',
              type: ColumnType.string
            }
          ]}
          rows={data}
        />
      </DataTable>
    </Box>
  );
};

// The cards, in the order they are displayed, each with its title and
// description: what the former visual snapshots showed.
const getCards = (): Array<HTMLElement> =>
  Array.from(document.querySelectorAll<HTMLElement>('[data-item-title]'));

const expectCardsInOrder = (): void => {
  const cards = getCards();

  expect(cards.map((card) => card.dataset.itemTitle)).toEqual(
    data.map(({ title }) => title)
  );
  cards.forEach((card, index) => {
    expect(within(card).getByText(data[index].title)).toBeVisible();
    expect(within(card).getByText(data[index].description)).toBeVisible();
  });

  // The Cypress screenshot showed a grid: equal cards, several per row, the
  // last ones wrapping onto a second row, in reading order.
  const boxes = cards.map((card) => card.getBoundingClientRect());
  const columns = boxes.filter(({ top }) => top === boxes[0].top).length;

  expect(columns).toBeGreaterThan(1);
  expect(columns).toBeLessThan(boxes.length);
  boxes.forEach((box, index) => {
    const column = index % columns;
    const row = Math.floor(index / columns);

    expect(box.width).toBe(boxes[0].width);
    expect(box.left).toBe(boxes[column].left);
    expect(box.top).toBe(boxes[row * columns].top);
    expect(box.top).toBeGreaterThanOrEqual(
      boxes[0].top + row * boxes[0].height
    );
  });
};

describe('DataTable: Grid', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays items with title and description only', async () => {
    initializeDataTableGrid({
      hasActions: false,
      hasCardAction: false
    });

    for (const { title, description } of data) {
      expect(await screen.findByText(title)).toBeVisible();
      expect(screen.getByText(description)).toBeVisible();
    }

    expectCardsInOrder();
    expect(screen.queryByLabelText('Delete')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('view')).not.toBeInTheDocument();
  });

  it('displays items with actions', async () => {
    initializeDataTableGrid({
      hasActions: true,
      hasCardAction: false
    });

    expect(await screen.findAllByLabelText('Delete')).toHaveLength(5);

    expectCardsInOrder();
    getCards().forEach((card) => {
      expect(within(card).getByLabelText('Delete')).toBeVisible();
    });
    expect(screen.queryByLabelText('view')).not.toBeInTheDocument();
  });

  it('displays items with card action only', async () => {
    initializeDataTableGrid({
      hasActions: false,
      hasCardAction: true
    });

    expect(await screen.findAllByLabelText('view')).toHaveLength(5);

    expectCardsInOrder();
    expect(screen.queryByLabelText('Delete')).not.toBeInTheDocument();
  });

  it('displays items with card action and bottom actions', async () => {
    initializeDataTableGrid({
      hasActions: true,
      hasCardAction: true
    });

    expect(await screen.findAllByLabelText('view')).toHaveLength(5);
    expect(screen.getAllByLabelText('Delete')).toHaveLength(5);

    expectCardsInOrder();
    getCards().forEach((card) => {
      expect(within(card).getByLabelText('view')).toBeVisible();
      expect(within(card).getByLabelText('Delete')).toBeVisible();
    });
  });
});

describe('DataTable: Empty', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the title', async () => {
    initializeDataTableEmpty();

    expect(await screen.findByText('Welcome')).toBeVisible();

    expect(screen.getByText('Description')).toBeVisible();
    expect(screen.queryByText('Create')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('create')).not.toBeInTheDocument();
  });

  it('displays the title and the action button', async () => {
    initializeDataTableEmpty(true);

    expect(await screen.findByText('Welcome')).toBeVisible();
    expect(screen.getByText('Create')).toBeVisible();

    expect(screen.getByText('Description')).toBeVisible();
    expect(screen.getByLabelText('create')).toBeEnabled();
    expect(screen.getByLabelText('create')).toHaveTextContent(/^Create$/);
  });
});

describe('DataTable: Listing', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the listing', async () => {
    initializeDataTableListing();

    for (const { title, description } of data) {
      expect(await screen.findByText(title)).toBeVisible();
      expect(screen.getByText(description)).toBeVisible();
    }

    expect(screen.getByText('Title')).toBeVisible();
    expect(screen.getByText('Description')).toBeVisible();
    // The rows, in order, after the header row.
    const rows = screen.getAllByRole('row');

    expect(rows).toHaveLength(data.length + 1);
    data.forEach(({ title, description }, index) => {
      expect(rows[index + 1]).toHaveTextContent(`${title}${description}`);
    });
  });
});
