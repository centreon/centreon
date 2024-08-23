import { useEffect, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import SettingsIcon from '@mui/icons-material/Settings';

import { IconButton, useSnackbar } from '@centreon/ui';

import {
  labelEditFilters,
  labelFilterCreated
} from '../../../translatedLabels';
import CreateFilterDialog from '../../Save/CreateFilterDialog';
import { createFilter } from '../../api';
import {
  currentFilterAtom,
  customFiltersAtom,
  editPanelOpenAtom,
  sendingFilterAtom
} from '../../filterAtoms';
import { Filter } from '../../models';

import useActionFilter from './useActionFilter';

const EditFilterButton = (): JSX.Element => {
  const { t } = useTranslation();

  const [createFilterDialogOpen, setCreateFilterDialogOpen] = useState(false);

  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);

  const setEditPanelOpen = useSetAtom(editPanelOpenAtom);
  const setSendingFilter = useSetAtom(sendingFilterAtom);

  const { showSuccessMessage } = useSnackbar();

  const { loadFiltersAndUpdateCurrent, sendingListCustomFiltersRequest } =
    useActionFilter();

  const closeCreateFilterDialog = (): void => {
    setCreateFilterDialogOpen(false);
  };

  const confirmCreateFilter = (newFilter: Filter): void => {
    showSuccessMessage(t(labelFilterCreated));
    closeCreateFilterDialog();

    loadFiltersAndUpdateCurrent(omit(['order'], newFilter));
  };

  const openEditPanel = (): void => {
    setEditPanelOpen(true);
  };

  useEffect(() => {
    setSendingFilter(sendingListCustomFiltersRequest);
  }, [sendingListCustomFiltersRequest]);

  return (
    <>
      <IconButton
        aria-label={t(labelEditFilters) as string}
        data-testid="Filter Edit filters"
        disabled={isEmpty(customFilters)}
        size="large"
        title={t(labelEditFilters) as string}
        onClick={openEditPanel}
      >
        <SettingsIcon />
      </IconButton>
      {createFilterDialogOpen && (
        <CreateFilterDialog
          open
          callbackSuccess={confirmCreateFilter}
          payloadAction={{ criterias: currentFilter.criterias }}
          request={createFilter}
          onCancel={closeCreateFilterDialog}
        />
      )}
    </>
  );
};

export default EditFilterButton;
