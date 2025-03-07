import { useState } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';
import { useNavigate } from 'react-router';
import { configurationAtom } from '../../atoms';
import { labelSelectAtLeastOneColumn } from '../translatedLabels';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  page?: number;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
  selectedColumnIds: Array<string>;
  setLimit;
  sortf: string;
  sorto: 'asc' | 'desc';
  openEditModal: (row) => void;
  disableRowCondition: (row) => boolean;
}

const useListing = (): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();
  const navigate = useNavigate();

  const configuration = useAtomValue(configurationAtom);
  const defaultSelectedColumnIds = configuration?.defaultSelectedColumnIds;

  const [selectedColumnIds, setSelectedColumnIds] = useState(
    defaultSelectedColumnIds
  );

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
    navigate(`/main.php?p=60102&o=c&hg_id=${row.id}`);
  };

  const disableRowCondition = ({ isActivated }): boolean => !isActivated;

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
    disableRowCondition
  };
};

export default useListing;
