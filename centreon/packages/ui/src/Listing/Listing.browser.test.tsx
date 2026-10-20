import { Button, Typography } from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';

import Listing from '.';
import {
  type Column,
  ColumnType,
  type ComponentColumnProps,
  type RowRecord
} from './models';

interface NamedRow {
  name: string;
}

const getName = (row: RowRecord): string => (row as unknown as NamedRow).name;

const ButtonColumn = ({ row }: ComponentColumnProps): JSX.Element => (
  <Button size="small">Click to reveal details about {getName(row)}</Button>
);

const CheckboxColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const [checked, setChecked] = useState(false);

  return (
    <input
      aria-label={`Toggle ${getName(row)}`}
      checked={checked}
      onChange={(event) => setChecked(event.target.checked)}
      type="checkbox"
    />
  );
};

const LargeText = (): JSX.Element => (
  <Typography sx={{ whiteSpace: 'normal' }}>
    This is a large text that fills the content
  </Typography>
);

interface Item {
  active: boolean;
  description: string;
  disableCheckbox: boolean;
  disableRow: boolean;
  id: number;
  name: string;
  selected: boolean;
  subItems?: Array<Item>;
}

const tenElements = new Array(10).fill(0);

const generateSubItems = (): Array<Item> => {
  return tenElements.map((_, subIndex) => ({
    active: false,
    description: `Sub item ${subIndex * 10} description`,
    disableCheckbox: false,
    disableRow: false,
    id: subIndex * 10,
    name: `Sub Item ${subIndex * 10}`,
    selected: false
  }));
};

const listingWithSubItems: Array<Item> = tenElements.map((_, index) => ({
  active: false,
  description: `Entity ${index}`,
  disableCheckbox: false,
  disableRow: false,
  id: index,
  name: `E${index}`,
  selected: false,
  subItems: index % 2 === 0 ? generateSubItems() : undefined
}));

const listingWithSubItems3Rows: Array<Item> = Array(3)
  .fill(0)
  .map((_, index) => ({
    active: false,
    description: `Entity ${index}`,
    disableCheckbox: false,
    disableRow: false,
    id: index,
    name: `E${index}`,
    selected: false,
    subItems: index % 2 === 0 ? generateSubItems() : undefined
  }));

const defaultColumn: Array<Column> = [
  {
    getFormattedString: getName,
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: (row: RowRecord): string =>
      (row as unknown as Item).description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  },
  {
    Component: LargeText,
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
    width: '100px'
  }
];

const columnsWithSubItems: Array<Column> = [
  {
    getFormattedString: getName,
    id: 'name',
    label: 'Name',
    type: ColumnType.string
  },
  {
    getFormattedString: (row: RowRecord): string =>
      (row as unknown as Item).description,
    id: 'description',
    label: 'Description',
    type: ColumnType.string
  },
  {
    Component: ButtonColumn,
    displaySubItemsCaret: true,
    id: '#',
    label: 'Custom',
    type: ColumnType.component,
    width: '350px'
  }
];

const expandedItems = [0, 8];

// Rendered in the 1280px-wide rstest viewport.
const mountListingResponsive = (listingVariant: ListingVariant): void => {
  render(
    <div style={{ height: '100vh' }}>
      <Listing
        columns={defaultColumn}
        currentPage={1}
        isResponsive
        limit={10}
        listingVariant={listingVariant}
        rows={listingWithSubItems}
        totalRows={10}
      />
    </div>
  );
};

interface TestComponentProps {
  canCheckSubItems?: boolean;
  isSmallListing?: boolean;
}

const TestComponent = ({
  isSmallListing = false,
  canCheckSubItems = false
}: TestComponentProps): JSX.Element => {
  const [selectedRows, setSelectedRows] = useState<Array<Item>>([]);

  return (
    <Listing
      checkable
      columns={columnsWithSubItems}
      currentPage={1}
      limit={10}
      onSelectRows={setSelectedRows}
      rows={isSmallListing ? listingWithSubItems3Rows : listingWithSubItems}
      selectedRows={selectedRows}
      subItems={{
        canCheckSubItems,
        enable: true,
        getRowProperty: () => 'subItems',
        labelCollapse: 'Collapse',
        labelExpand: 'Expand'
      }}
      totalRows={10}
    />
  );
};

const mountListingForSubItems = ({
  isSmallListing = false,
  canCheckSubItems = false
}: TestComponentProps): void => {
  render(
    <div style={{ height: '100vh' }}>
      <TestComponent
        canCheckSubItems={canCheckSubItems}
        isSmallListing={isSmallListing}
      />
    </div>
  );
};

// Every row collapsed: the headers, the ten rows in order, an expand caret on
// the rows with sub items only, and no sub item.
const expectCollapsedListing = (): void => {
  ['Name', 'Description', 'Custom'].forEach((label) => {
    expect(screen.getByText(label)).toBeVisible();
  });

  listingWithSubItems.forEach(({ id, name, description, subItems }) => {
    expect(screen.getByText(name)).toBeVisible();
    expect(screen.getByText(description)).toBeVisible();
    expect(
      screen.getByText(`Click to reveal details about ${name}`)
    ).toBeVisible();

    if (subItems) {
      expect(screen.getByLabelText(`Expand ${id}`)).toBeVisible();
    } else {
      expect(screen.queryByLabelText(`Expand ${id}`)).not.toBeInTheDocument();
    }
    expect(screen.queryByLabelText(`Collapse ${id}`)).not.toBeInTheDocument();
  });

  expect(screen.queryByText(/^Sub Item/)).not.toBeInTheDocument();
  expect(screen.queryByText(/^Sub item/)).not.toBeInTheDocument();

  const names = screen
    .getAllByText(/^E\d$/)
    .map((element) => element.textContent);

  expect(names).toEqual(listingWithSubItems.map(({ name }) => name));
};

// The headers, the rows in order and the large text of the last column on
// each row.
const expectResponsiveListing = (): void => {
  ['Name', 'Description', 'Custom'].forEach((label) => {
    expect(screen.getByText(label)).toBeVisible();
  });
  expect(
    screen.getAllByText('This is a large text that fills the content')
  ).toHaveLength(10);

  const names = screen
    .getAllByText(/^E\d$/)
    .map((element) => element.textContent);

  expect(names).toEqual(listingWithSubItems.map(({ name }) => name));
};

const getTable = (): HTMLElement => {
  const table = document.querySelector<HTMLElement>('.MuiTable-root');

  if (!table) {
    throw new Error('No table');
  }

  return table;
};

describe('Listing', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  describe('Sub items', () => {
    it('expands the row when the corresponding icon is clicked', async () => {
      mountListingForSubItems({});

      expect(await screen.findByText('E0')).toBeVisible();

      for (const index of expandedItems) {
        const subItems = generateSubItems();

        await userEvent.click(screen.getByLabelText(`Expand ${index}`));

        expect(
          await screen.findByLabelText(`Collapse ${index}`)
        ).toBeInTheDocument();

        for (const { name, description } of subItems) {
          expect(await screen.findByText(name)).toBeInTheDocument();
          expect(screen.getByText(description)).toBeInTheDocument();
        }

        await userEvent.click(screen.getByLabelText(`Collapse ${index}`));
      }

      await waitFor(() => {
        expect(screen.queryByText('Sub Item 0')).not.toBeInTheDocument();
      });
      expectCollapsedListing();
    });

    it('collapses the row when the corresponding icon is clicked', async () => {
      mountListingForSubItems({});

      await userEvent.click(await screen.findByLabelText('Expand 0'));

      expect(await screen.findByText('Sub item 10 description')).toBeVisible();

      await userEvent.click(screen.getByLabelText('Collapse 0'));

      await waitFor(() => {
        expect(screen.queryByText(/Sub item 10/)).not.toBeInTheDocument();
      });

      expectCollapsedListing();
    });

    it('displays only one row as hovered when mutilple rows are expanded and a sub item row is hovered', async () => {
      mountListingForSubItems({ isSmallListing: true });

      await userEvent.click(await screen.findByLabelText('Expand 2'));
      await userEvent.click(await screen.findByLabelText('Expand 0'));

      expect(await screen.findByLabelText('Collapse 0')).toBeVisible();
      expect(await screen.findByLabelText('Collapse 2')).toBeVisible();

      await userEvent.hover(screen.getAllByText('Sub Item 0')[0]);

      await waitFor(() => {
        expect(
          document.querySelectorAll('[data-is-hovered="true"]')
        ).toHaveLength(1);
      });
      const hoveredRow = document.querySelector<HTMLElement>(
        '[data-is-hovered="true"]'
      );

      expect(
        within(hoveredRow as HTMLElement).getByText('Sub Item 0')
      ).toBeInTheDocument();

      await userEvent.click(screen.getByLabelText('Collapse 0'));
      await userEvent.click(screen.getByLabelText('Collapse 2'));
    });

    it('selects displayed rows when a row is selected and another row is selected with the shift key', async () => {
      const user = userEvent.setup();

      mountListingForSubItems({ canCheckSubItems: true, isSmallListing: true });

      await user.click(await screen.findByLabelText('Expand 2'));

      expect(await screen.findByLabelText('Collapse 2')).toBeVisible();

      await user.click(screen.getAllByLabelText('Select row 2')[0]);
      await user.keyboard('{Shift>}');
      await user.click(screen.getByLabelText('Select row 50'));
      await user.keyboard('{/Shift}');

      await waitFor(() => {
        expect(screen.getByLabelText('Select row 40')).toBeChecked();
      });
      expect(screen.getAllByLabelText('Select row 2')[0]).toBeChecked();
      expect(screen.getByLabelText('Select row 10')).toBeChecked();
      expect(screen.getByLabelText('Select row 20')).toBeChecked();
      expect(screen.getByLabelText('Select row 30')).toBeChecked();
      expect(screen.getByLabelText('Select row 40')).toBeChecked();

      await user.click(screen.getByLabelText('Collapse 2'));
    });

    it('selects displayed rows when the corresponding checkbox is clicked', async () => {
      mountListingForSubItems({ canCheckSubItems: true, isSmallListing: true });

      await userEvent.click(await screen.findByLabelText('Expand 2'));

      expect(await screen.findByLabelText('Collapse 2')).toBeVisible();

      await userEvent.click(screen.getAllByLabelText('Select all')[0]);

      await waitFor(() => {
        expect(screen.getAllByLabelText('Select row 2')[0]).toBeChecked();
      });
      tenElements.forEach((_, index) => {
        if (index === 0) {
          expect(screen.getAllByLabelText('Select row 0')[1]).toBeChecked();

          return;
        }
        expect(screen.getByLabelText(`Select row ${index * 10}`)).toBeChecked();
      });
      expect(screen.getByLabelText('Select row 1')).toBeChecked();
      expect(screen.getByLabelText('Select row 2')).toBeChecked();

      await userEvent.click(screen.getByLabelText('Collapse 2'));
    });
  });

  it('displays the last column on several lines in compact mode when the isResponsive prop is set', async () => {
    mountListingResponsive(ListingVariant.compact);

    await waitFor(() => {
      expect(getComputedStyle(getTable()).gridTemplateRows).toBe(
        '30px 85px 85px 85px 85px 85px 85px 85px 85px 85px 85px'
      );
    });

    expectResponsiveListing();
  });

  it('displays the last column on several lines in extended mode when the isResponsive prop is set', async () => {
    mountListingResponsive(ListingVariant.extended);

    await waitFor(() => {
      expect(getComputedStyle(getTable()).gridTemplateRows).toBe(
        '38px 85px 85px 85px 85px 85px 85px 85px 85px 85px 85px'
      );
    });

    expectResponsiveListing();
  });

  it('toggles a checkbox in a clickable column without clicking the row', async () => {
    const onRowClick = rs.fn();

    render(
      <div style={{ height: '100vh' }}>
        <Listing
          columns={[
            {
              Component: CheckboxColumn,
              clickable: true,
              id: 'toggle',
              label: 'Toggle',
              type: ColumnType.component
            }
          ]}
          currentPage={1}
          limit={10}
          onRowClick={onRowClick}
          rows={[{ id: 0, name: 'E0' }]}
          totalRows={1}
        />
      </div>
    );

    await userEvent.click(await screen.findByLabelText('Toggle E0'));

    await waitFor(() => {
      expect(screen.getByLabelText('Toggle E0')).toBeChecked();
    });
    expect(onRowClick).not.toHaveBeenCalled();
  });
});
