// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  configurationAtom,
  formStateAtom,
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom
} from '../atoms';
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
  openEditForm: (row) => void;
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

  const [formState, setFormState] = useAtom(formStateAtom);
  const isFormDirty = useAtomValue(isFormDirtyAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );

  // `MemoizedListing` holds on to this handler, so anything it reads has to be
  // read when the row is clicked rather than when the handler was built.
  const formStateRef = useRef(formState);
  formStateRef.current = formState;
  const isFormDirtyRef = useRef(isFormDirty);
  isFormDirtyRef.current = isFormDirty;
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

  const openEditForm = (row) => {
    // A panel has no backdrop, so the listing stays clickable while a form is
    // open. Switching to another resource would drop unsaved edits silently.
    const openForm = formStateRef.current;

    const leavesEditsBehind =
      openForm.isOpen && isFormDirtyRef.current && openForm.id !== row.id;

    if (leavesEditsBehind) {
      setIsCloseConfirmationDialogOpen(true);

      return;
    }

    setSearchParams({ id: row.id, mode: 'edit' });

    setFormState({
      id: row.id,
      isOpen: true,
      mode: 'edit',
      resource: row
    });
  };

  const disableRowCondition = ({ isActivated }): boolean =>
    actions?.enableDisable && !isActivated;

  return {
    changePage,
    changeSort,
    disableRowCondition,
    limit,
    openEditForm,
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
