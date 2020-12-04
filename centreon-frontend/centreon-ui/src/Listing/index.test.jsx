import React from 'react';

import { render, fireEvent } from '@testing-library/react';

import { ColumnType } from './models';

import Table from '.';

describe('Table', () => {
  const getAllCheckboxes = (container) => {
    return container.querySelectorAll('[type = "checkbox"]');
  };

  const columnConfiguration = [
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

  const tableData = [
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
      <Table
        onSort={onSort}
        columnConfiguration={columnConfiguration}
        tableData={oneHundredTableData}
        totalRows={oneHundredTableData.length}
        limit={limit}
        currentPage={page}
        onPaginate={(_, value) => setPage(value)}
        onPaginationLimitChanged={({ target }) => setLimit(target.value)}
      />
    );
  };

  it('selects a row when the corresponding checkbox is clicked', () => {
    const { container } = render(
      <Table
        onSelectRows={onSelectRows}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
        checkable
      />,
    );

    // The first visible checkbox is the 'select all' one
    const firstRowCheckbox = getAllCheckboxes(container)[1];

    fireEvent.click(firstRowCheckbox);

    const firstRow = tableData[0];
    expect(onSelectRows).toHaveBeenCalledWith([firstRow]);
  });

  it('unselects a row when it is currently selected and the corresponding checkbox is clicked', () => {
    const firstRow = tableData[0];
    const selectedRows = [firstRow];

    const { container } = render(
      <Table
        onSelectRows={onSelectRows}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
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
      <Table
        onSelectRows={onSelectRows}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
        totalRows={4}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenLastCalledWith(tableData);
  });

  it('unselects all rows when all rows are selected and the "select all" checkbox is clicked', () => {
    const { container } = render(
      <Table
        onSelectRows={onSelectRows}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
        selectedRows={tableData}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('unselects selected rows when some rows are selected and the "select all" checkbox is clicked', () => {
    const selectedRows = tableData.filter(({ id }) => id % 2 === 0);
    const { container } = render(
      <Table
        onSelectRows={onSelectRows}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
        selectedRows={selectedRows}
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith([]);
  });

  it('sorts on on column id when the column header is clicked and sortField is not defined', () => {
    const columnWithoutSortField = columnConfiguration[0];

    const { getByLabelText } = render(
      <Table
        onSort={onSort}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
      />,
    );

    fireEvent.click(getByLabelText(`Column ${columnWithoutSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      order: 'desc',
      orderBy: columnWithoutSortField.id,
    });
  });

  it('sorts on on column sortField when the column header is clicked and sortField is defined', () => {
    const columnWithSortField = columnConfiguration[1];

    const { getByLabelText } = render(
      <Table
        onSort={onSort}
        columnConfiguration={columnConfiguration}
        tableData={tableData}
      />,
    );

    fireEvent.click(getByLabelText(`Column ${columnWithSortField.label}`));

    expect(onSort).toHaveBeenCalledWith({
      order: 'desc',
      orderBy: columnWithSortField.sortField,
    });
  });

  it('resets the page number to 0 when changing the limit and the current page is different than 0', () => {
    const { container, getByText } = render(<PaginationTable />);

    expect(getByText('41-50 of 100'));

    fireEvent.change(container.querySelector('select'), {
      target: {
        value: 90,
      },
    });

    expect(getByText('1-90 of 100'));
  });
});
