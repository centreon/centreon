import { useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { configurationAtom, modalStateAtom } from '../atoms';
import { labelSelectAtLeastOneColumn } from '../translatedLabels';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

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
  limit: number;
}

const useListing = ({ selectedColumnIdsAtom }): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const [, setSearchParams] = useSearchParams();

  const configuration = useAtomValue(configurationAtom);
  const defaultSelectedColumnIds = configuration?.defaultSelectedColumnIds;
  const actions = configuration?.actions;

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const setModalState = useSetAtom(modalStateAtom);
  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);

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
    setSearchParams({ id: row.id, mode: 'edit' });

    setModalState({
      id: row.id,
      isOpen: true,
      mode: 'edit'
    });
  };

  const disableRowCondition = ({ isActivated }): boolean =>
    actions?.enableDisable && !isActivated;

  return {
    changePage,
    changeSort,
    disableRowCondition,
    limit,
    openEditModal,
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
