import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';
import { labelSelectAtLeastOneColumn } from '../translatedLabels';
import { defaultSelectedColumnIds } from './Columns/Columns';
import {
  limitAtom,
  pageAtom,
  selectedColumnIdsAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';

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
  disableRowCondition: (row) => boolean;
}

const useListing = (): UseListing => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
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
    if (updatedColumnIds.length < 1) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const disableRowCondition = ({ isRevoked }): boolean => isRevoked;

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
    disableRowCondition
  };
};

export default useListing;
