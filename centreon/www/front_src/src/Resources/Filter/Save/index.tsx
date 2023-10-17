import { MouseEvent, useEffect, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, omit } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import SettingsIcon from '@mui/icons-material/Settings';
import { CircularProgress, Menu, MenuItem } from '@mui/material';

import { IconButton, useSnackbar } from '@centreon/ui';

import {
  labelEditFilters,
  labelFilterCreated,
  labelSave,
  labelSaveAsNew,
  labelSaveFilter
} from '../../translatedLabels';
import {
  currentFilterAtom,
  customFiltersAtom,
  editPanelOpenAtom,
  sendingFilterAtom
} from '../filterAtoms';
import { Filter } from '../models';
import { createFilter } from '../api';

import CreateFilterDialog from './CreateFilterDialog';
import useActionFilter from './useActionFilter';

const useStyles = makeStyles()((theme) => ({
  save: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(2)
  }
}));

const SaveFilterMenu = (): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const [menuAnchor, setMenuAnchor] = useState<Element | null>(null);
  const [createFilterDialogOpen, setCreateFilterDialogOpen] = useState(false);

  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);

  const setEditPanelOpen = useSetAtom(editPanelOpenAtom);
  const setSendingFilter = useSetAtom(sendingFilterAtom);

  const { showSuccessMessage } = useSnackbar();

  const closeSaveFilterMenu = (): void => {
    setMenuAnchor(null);
  };

  const {
    canSaveFilter,
    canSaveFilterAsNew,
    loadFiltersAndUpdateCurrent,
    sendingListCustomFiltersRequest,
    updateFilter,
    sendingUpdateFilterRequest
  } = useActionFilter();
  const openSaveFilterMenu = (event: MouseEvent<HTMLButtonElement>): void => {
    setMenuAnchor(event.currentTarget);
  };

  const openCreateFilterDialog = (): void => {
    closeSaveFilterMenu();
    setCreateFilterDialogOpen(true);
  };

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
    closeSaveFilterMenu();
  };

  useEffect(() => {
    setSendingFilter(sendingListCustomFiltersRequest);
  }, [sendingListCustomFiltersRequest]);

  return (
    <>
      <IconButton
        aria-label={t(labelSaveFilter) as string}
        data-testid={labelSaveFilter}
        size="large"
        title={t(labelSaveFilter) as string}
        onClick={openSaveFilterMenu}
      >
        <SettingsIcon />
      </IconButton>
      <Menu
        keepMounted
        anchorEl={menuAnchor}
        open={Boolean(menuAnchor)}
        onClose={closeSaveFilterMenu}
      >
        <MenuItem
          data-testid="Filter Save as new"
          disabled={!canSaveFilterAsNew}
          onClick={openCreateFilterDialog}
        >
          {t(labelSaveAsNew)}
        </MenuItem>
        <MenuItem
          data-testid="Filter Save"
          disabled={!canSaveFilter}
          onClick={updateFilter}
        >
          <div className={classes.save}>
            <span>{t(labelSave)}</span>
            {sendingUpdateFilterRequest && <CircularProgress size={15} />}
          </div>
        </MenuItem>
        <MenuItem
          data-testid="Filter Edit filters"
          disabled={isEmpty(customFilters)}
          onClick={openEditPanel}
        >
          {t(labelEditFilters)}
        </MenuItem>
      </Menu>
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

export default SaveFilterMenu;
