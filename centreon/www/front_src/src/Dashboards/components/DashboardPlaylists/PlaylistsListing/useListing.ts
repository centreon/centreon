import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { prop, equals } from 'ramda';
import { useNavigate } from 'react-router';

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
  linkToPlaylist: (row) => void;
  page?: number;
  predefinedRowsSelection;
  resetColumns: () => void;
  selectedColumnIds: Array<string>;
  selectedRows: Array<PlaylistType>;
  setLimit;
  setSelectedColumnIds;
  setSelectedRows;
  sortf: string;
  sorto: 'asc' | 'desc';
}

const useListing = ({ columns }): UseListing => {
  const navigate = useNavigate();

  const [selectedColumnIds, setSelectedColumnIds] = useState<Array<string>>(
    columns.map(prop('id'))
  );

  const resetColumns = (): void => {
    setSelectedColumnIds(columns.map(prop('id')));
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

  const predefinedRowsSelection = [
    {
      label: 'activated',
      rowCondition: (row): boolean => row?.isActivated
    },
    {
      label: 'deactivated',
      rowCondition: (row): boolean => !row?.isActivated
    }
  ];

  const getRowProperty = (row): string => {
    if (equals(row?.ownRole, Role.Viewer)) {
      return '';
    }

    return 'shares';
  };

  const linkToPlaylist = (row): void => {
    navigate(`/home/dashboards/playlists/${row?.id}`);
  };

  return {
    changePage,
    changeSort,
    getRowProperty,
    linkToPlaylist,
    page,
    predefinedRowsSelection,
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
