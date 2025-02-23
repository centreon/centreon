import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { useTheme } from '@mui/material';
import { useSearchParams } from 'react-router';

import { configurationAtom, modalStateAtom } from '../atoms';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

import { labelSelectAtLeastOneColumn } from '../translatedLabels';

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  page?: number;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds?: Array<string>;
  setLimit;
  sortf: string;
  sorto: 'asc' | 'desc';
  openEditModal: (row) => void;
  rowColorConditions;
}

const useListing = (): UseListing => {
  const { t } = useTranslation();
  const theme = useTheme();
  const { showWarningMessage } = useSnackbar();

  const [, setSearchParams] = useSearchParams();

  const configuration = useAtomValue(configurationAtom);
  const defaultSelectedColumnIds = configuration?.defaultSelectedColumnIds;

  const [selectedColumnIds, setSelectedColumnIds] = useState(
    defaultSelectedColumnIds
  );

  const setModalState = useSetAtom(modalStateAtom);
  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length < 3) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const openEditModal = (row) => {
    setSearchParams({ mode: 'edit', id: row.id });

    setModalState({
      isOpen: true,
      mode: 'edit',
      id: row.id
    });
  };

  const rowColorConditions = [
    {
      color: theme.palette.action.disabledBackground,
      condition: ({ isActivated }): boolean => !isActivated,
      name: 'is_enabled'
    }
  ];

  return {
    changePage,
    changeSort,
    page,
    resetColumns,
    selectColumns,
    selectedColumnIds,
    setLimit,
    sortf,
    sorto,
    openEditModal,
    rowColorConditions
  };
};

export default useListing;
