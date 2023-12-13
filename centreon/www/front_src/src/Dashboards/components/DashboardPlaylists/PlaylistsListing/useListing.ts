import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { Role, PlaylistType } from './models';
import {
  pageAtom,
  limitAtom,
  sortOrderAtom,
  sortFieldAtom,
  selectedRowsAtom
} from './atom';

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  getRowProperty: (row) => string;
  page?: number;
  resetColumns: () => void;
  selectedColumnIds: Array<string>;
  selectedRows: Array<PlaylistType>;
  setLimit;
  setSelectedColumnIds;
  setSelectedRows;
  sortf: string;
  sorto: 'asc' | 'desc';
}

const useListing = ({
  defaultColumnsIds
}: {
  defaultColumnsIds: Array<string>;
}): UseListing => {
  const [selectedColumnIds, setSelectedColumnIds] =
    useState<Array<string>>(defaultColumnsIds);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultColumnsIds);
  };

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const getRowProperty = (row): string => {
    if (equals(row?.ownRole, Role.Viewer)) {
      return '';
    }

    return 'shares';
  };

  return {
    changePage,
    changeSort,
    getRowProperty,
    page,
    resetColumns,
    selectedColumnIds,
    selectedRows,
    setLimit,
    setSelectedColumnIds,
    setSelectedRows,
    sortf,
    sorto
  };
};

export default useListing;
