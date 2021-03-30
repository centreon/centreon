import React from 'react';

import { render, fireEvent } from '@testing-library/react';
import { prop } from 'ramda';

import { ColumnType } from './models';
import { labelAddColumns } from './translatedLabels';

import Listing from '.';

describe('Listing', () => {
  const getAllCheckboxes = (container) => {
    return container.querySelectorAll('[type = "checkbox"]');
  };

  const columns = [
    {
      id: 'name',
      label: 'name',
      type: ColumnType.string,
      getFormattedString: ({ name }) => name,
      sortable: true,
    },
    {
      id: 'description',
      label: 'description',
      type: ColumnType.string,
      getFormattedString: ({ description }) => description,
      sortable: true,
      sortField: 'descriptionField',
    },
  ];

  const rows = [
    { id: 0, name: 'My First Row', description: 'first row description' },
    { id: 1, name: 'My Second Row', description: 'second row description' },
    { id: 2, name: 'My Third Row', description: 'third row description' },
    { id: 3, name: 'My Fourth Row', description: 'fourth row description' },
  ];
  const onSelectRows = jest.fn();
  const onSort = jest.fn();

  const oneHundredElements = new Array(100).fill(0);

  const oneHundredTableData = [...oneHundredElements].map((_, index) => ({
    id: index,
    name: `E${index}`,
    description: `Entity ${index}`,
    active: index % 2 === 0,
    selected: index % 3 === 0,
    disableCheckbox: index % 4 === 0,
  }));

  const PaginationTable = () => {
    const [limit, setLimit] = React.useState(10);
    const [page, setPage] = React.useState(4);

    return (
      <Listing
        onSort={onSort}
        columns={columns}
        rows={oneHundredTableData}
        totalRows={oneHundredTableData.length}
        limit={limit}
        currentPage={page}
        onPaginate={setPage}
        onLimitChange={setLimit}
      />
    );
  };

  it('selects a row when the corresponding checkbox is clicked', () => {
    const { container } = render(
      <Listing
        onSelectRows={onSelectRows}
        columns={columns}
        rows={rows}
        checkable
      />,
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
        onSelectRows={onSelectRows}
        columns={columns}
        rows={rows}
        selectedRows={selectedRows}
        checkable
      />,
    );
    const firstRowCheckbox = getAllCheckboxes(container)[1];

    fireEvent.click(firstRowCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('selects all rows when the "select all" checkbox is clicked', () => {
    const { container } = render(
      <Listing
        onSelectRows={onSelectRows}
        columns={columns}
        rows={rows}
        totalRows={4}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenLastCalledWith(rows);
  });

  it('unselects all rows when all rows are selected and the "select all" checkbox is clicked', () => {
    const { container } = render(
      <Listing
        onSelectRows={onSelectRows}
        columns={columns}
        rows={rows}
        selectedRows={rows}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('unselects selected rows when some rows are selected and the "select all" checkbox is clicked', () => {
    const selectedRows = rows.filter(({ id }) => id % 2 === 0);
    const { container } = render(
      <Listing
        onSelectRows={onSelectRows}
        columns={columns}
        rows={rows}
        selectedRows={selectedRows}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('sorts on on column id when the column header is clicked and sortField is not defined', () => {
    const columnWithoutSortField = columns[0];

    const { getByLabelText } = render(
      <Listing onSort={onSort} columns={columns} rows={rows} />,
    );

    fireEvent.click(getByLabelText(`Column ${columnWithoutSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      sortOrder: 'desc',
      sortField: columnWithoutSortField.id,
    });
  });

  it('sorts on on column sortField when the column header is clicked and sortField is defined', () => {
    const columnWithSortField = columns[1];

    const { getByLabelText } = render(
      <Listing onSort={onSort} columns={columns} rows={rows} />,
    );

    fireEvent.click(getByLabelText(`Column ${columnWithSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      sortOrder: 'desc',
      sortField: columnWithSortField.sortField,
    });
  });

  it('resets the page number to 0 when changing the limit and the current page is different than 0', () => {
    const { container, getByText } = render(<PaginationTable />);

    expect(getByText('41-50 of 100'));

    fireEvent.change(container.querySelector('select') as HTMLSelectElement, {
      target: {
        value: 90,
      },
    });

    expect(getByText('1-90 of 100'));
  });

  it(`unselects a column when the "selectable" parameter is set and a column is unselected from the corresponding menu`, () => {
    const onSelectColumns = jest.fn();

    const { getAllByText, getByTitle } = render(
      <Listing
        onSort={onSort}
        columns={columns}
        rows={rows}
        columnConfiguration={{
          sortable: false,
          selectedColumnIds: columns.map(prop('id')),
        }}
        onSelectColumns={onSelectColumns}
      />,
    );

    fireEvent.click(getByTitle(labelAddColumns).firstChild as HTMLElement);

    fireEvent.click(getAllByText('description')[1]);

    expect(onSelectColumns).toHaveBeenCalledWith(['name']);
  });
});
