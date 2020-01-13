import React from 'react';
import { render, fireEvent } from '@testing-library/react';

import Table from '.';
import ColumnTypes from './ColumnTypes';

describe('Table', () => {
  const getAllCheckboxes = (container) => {
    return container.querySelectorAll('[type = "checkbox"]');
  };

  const columnConfiguration = [
    {
      id: 'name',
      type: ColumnTypes.string,
    },
  ];

  const tableData = [{ name: 'My First Row' }, { name: 'My Second Row' }];
  const onSelectRows = jest.fn();

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
        checkable
      />,
    );

    const selectAllCheckbox = getAllCheckboxes(container)[0];

    fireEvent.click(selectAllCheckbox);

    expect(onSelectRows).toHaveBeenCalledWith(tableData);
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
});
