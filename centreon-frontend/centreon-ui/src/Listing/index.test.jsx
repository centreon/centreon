import React from 'react';
import { render, fireEvent } from '@testing-library/react';

import Table from '.';
import { ColumnType } from './models';

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
});
