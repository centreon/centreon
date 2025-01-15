import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { ListingModel, useSnackbar } from '@centreon/ui';

import { dialogStateAtom } from '../atoms';
import { labelSelectAtLeastOneColumn } from '../translatedLabels';

import { defaultSelectedColumnIds } from './Columns';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atom';
import { AdditionalConnectorListItem } from './models';
import useLoadData from './useLoadData';

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  data?: ListingModel<AdditionalConnectorListItem>;
  isLoading: boolean;
  openEditDialog: (connector: AdditionalConnectorListItem) => void;
  page?: number;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
  setLimit;
  sortf: string;
  sorto: 'asc' | 'desc';
}

const useListing = (): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();
  const [selectedColumnIds, setSelectedColumnIds] = useState(
    defaultSelectedColumnIds
  );

  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);
  const setDialogState = useSetAtom(dialogStateAtom);

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

  const openEditDialog = (connector: AdditionalConnectorListItem): void => {
    setDialogState({
      connector,
      isOpen: true,
      variant: 'update'
    });
  };

  const { isLoading, data } = useLoadData();

  return {
    changePage,
    changeSort,
    data,
    isLoading,
    openEditDialog,
    page,
    resetColumns,
    selectColumns,
    selectedColumnIds,
    setLimit,
    sortf,
    sorto
  };
};

export default useListing;
