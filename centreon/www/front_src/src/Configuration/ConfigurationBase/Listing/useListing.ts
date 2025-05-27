import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { useSearchParams } from 'react-router';

import {
  configurationAtom,
  modalStateAtom,
  selectedColumnIdsAtom
} from '../atoms';
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
  disableRowCondition: (row) => boolean;
}

const useListing = (): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const [, setSearchParams] = useSearchParams();

  const configuration = useAtomValue(configurationAtom);
  const defaultSelectedColumnIds = configuration?.defaultSelectedColumnIds;

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
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
    if (updatedColumnIds.length < 1) {
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
