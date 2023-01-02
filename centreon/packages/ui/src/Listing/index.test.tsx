import { useState } from 'react';

import { prop } from 'ramda';

import { render, fireEvent, within } from '../testRenderer';

import { ColumnType } from './models';
import { labelAddColumns } from './translatedLabels';

import Listing from '.';

describe('Listing', () => {
  const getAllCheckboxes = (container): Array<HTMLElement> => {
    return container.querySelectorAll('[type = "checkbox"]');
  };

  const columns = [
    {
      getFormattedString: ({ name }): string => name,
      id: 'name',
      label: 'name',
      sortable: true,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ description }): string => description,
      id: 'description',
      label: 'description',
      sortField: 'descriptionField',
      sortable: true,
      type: ColumnType.string
    }
  ];

  const rows = [
    { description: 'first row description', id: 0, name: 'My First Row' },
    { description: 'second row description', id: 1, name: 'My Second Row' },
    { description: 'third row description', id: 2, name: 'My Third Row' },
    { description: 'fourth row description', id: 3, name: 'My Fourth Row' }
  ];

  const onSelectRows = jest.fn();
  const onSort = jest.fn();

  const oneHundredElements = new Array(100).fill(0);

  const oneHundredTableData = [...oneHundredElements].map((_, index) => ({
    active: index % 2 === 0,
    description: `Entity ${index}`,
    disableCheckbox: index % 4 === 0,
    id: index,
    name: `E${index}`,
    selected: index % 3 === 0
  }));

  const PaginationTable = (): JSX.Element => {
    const [limit, setLimit] = useState(10);
    const [page, setPage] = useState(4);

    return (
      <Listing
        columns={columns}
        currentPage={page}
        limit={limit}
        rows={oneHundredTableData}
        totalRows={oneHundredTableData.length}
        onLimitChange={setLimit}
        onPaginate={setPage}
        onSort={onSort}
      />
    );
  };

  it('selects a row when the corresponding checkbox is clicked', () => {
    const { container } = render(
      <Listing
        checkable
        columns={columns}
        rows={rows}
        onSelectRows={onSelectRows}
      />
    );

    // The first visible checkbox is the 'select all' one
    const firstRowCheckbox = getAllCheckboxes(container)[1];

    fireEvent.click(firstRowCheckbox);

    const firstRow = rows[0];
    expect(onSelectRows).toHaveBeenCalledWith([firstRow]);
  });

  it('unselects a row when it is currently selected and the corresponding checkbox is clicked', () => {
    const firstRow = rows[0];
    const selectedRows = [firstRow];

    const { container } = render(
      <Listing
        checkable
        columns={columns}
        rows={rows}
        selectedRows={selectedRows}
        onSelectRows={onSelectRows}
      />
    );
    const firstRowCheckbox = getAllCheckboxes(container)[1];

    fireEvent.click(firstRowCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('selects all rows when the "select all" checkbox is clicked', () => {
    const { container } = render(
      <Listing
        checkable
        columns={columns}
        rows={rows}
        totalRows={4}
        onSelectRows={onSelectRows}
      />
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenLastCalledWith(rows);
  });

  it('unselects all rows when all rows are selected and the "select all" checkbox is clicked', () => {
    const { container } = render(
      <Listing
        checkable
        columns={columns}
        rows={rows}
        selectedRows={rows}
        onSelectRows={onSelectRows}
      />
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('unselects selected rows when some rows are selected and the "select all" checkbox is clicked', () => {
    const selectedRows = rows.filter(({ id }) => id % 2 === 0);
    const { container } = render(
      <Listing
        checkable
        columns={columns}
        rows={rows}
        selectedRows={selectedRows}
        onSelectRows={onSelectRows}
      />
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('sorts on on column id when the column header is clicked and sortField is not defined', () => {
    const columnWithoutSortField = columns[0];

    const { getByLabelText } = render(
      <Listing columns={columns} rows={rows} onSort={onSort} />
    );

    fireEvent.click(getByLabelText(`Column ${columnWithoutSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      sortField: columnWithoutSortField.id,
      sortOrder: 'desc'
    });
  });

  it('sorts on on column sortField when the column header is clicked and sortField is defined', () => {
    const columnWithSortField = columns[1];

    const { getByLabelText } = render(
      <Listing columns={columns} rows={rows} onSort={onSort} />
    );

    fireEvent.click(getByLabelText(`Column ${columnWithSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      sortField: columnWithSortField.sortField,
      sortOrder: 'desc'
    });
  });

  it('resets the page number to 0 when changing the limit and the current page is different than 0', () => {
    const { getByLabelText, getByRole, getByText } = render(
      <PaginationTable />
    );

    expect(getByText('41-50 of 100'));

    fireEvent.mouseDown(getByLabelText('Rows per page'));

    const listbox = within(getByRole('listbox'));

    fireEvent.click(listbox.getByText('90'));

    expect(getByText('1-90 of 100'));
  });

  it(`unselects a column when the "selectable" parameter is set and a column is unselected from the corresponding menu`, () => {
    const onSelectColumns = jest.fn();

    const { getAllByText, getByLabelText } = render(
      <Listing
        columnConfiguration={{
          selectedColumnIds: columns.map(prop('id')),
          sortable: false
        }}
        columns={columns}
        rows={rows}
        onSelectColumns={onSelectColumns}
        onSort={onSort}
      />
    );

    fireEvent.click(getByLabelText(labelAddColumns).firstChild as HTMLElement);

    fireEvent.click(getAllByText('description')[1]);

    expect(onSelectColumns).toHaveBeenCalledWith(['name']);
  });
});
