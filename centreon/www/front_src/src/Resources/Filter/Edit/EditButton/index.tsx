import SettingsIcon from '@mui/icons-material/Settings';

import { IconButton, useSnackbar } from '@centreon/ui';

import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, omit } from 'ramda';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelFilterCreated,
  labelManageFilters
} from '../../../translatedLabels';
import { createFilter } from '../../api';
import {
  currentFilterAtom,
  customFiltersAtom,
  editPanelOpenAtom,
  sendingFilterAtom
} from '../../filterAtoms';
import { Filter } from '../../models';
import CreateFilterDialog from '../../Save/CreateFilterDialog';
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
        aria-label={t(labelManageFilters) as string}
        data-testid="Filter Manage filters"
        disabled={isEmpty(customFilters)}
        onClick={openEditPanel}
        size="large"
        title={t(labelManageFilters) as string}
      >
        <SettingsIcon />
      </IconButton>
      {createFilterDialogOpen && (
        <CreateFilterDialog
          callbackSuccess={confirmCreateFilter}
          onCancel={closeCreateFilterDialog}
          open
          payloadAction={{ criterias: currentFilter.criterias }}
          request={createFilter}
        />
      )}
    </>
  );
};

export default EditFilterButton;
