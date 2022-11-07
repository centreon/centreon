<<<<<<< HEAD
import { MouseEvent, useEffect, useState } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import {
  or,
  and,
  not,
  isEmpty,
  omit,
  find,
  propEq,
  pipe,
  symmetricDifference,
} from 'ramda';
import { useTranslation } from 'react-i18next';
<<<<<<< HEAD
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { useAtom } from 'jotai';

import { Menu, MenuItem, CircularProgress } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import SettingsIcon from '@mui/icons-material/Settings';
=======

import {
  Menu,
  MenuItem,
  CircularProgress,
  makeStyles,
} from '@material-ui/core';
import SettingsIcon from '@material-ui/icons/Settings';
>>>>>>> centreon/dev-21.10.x

import { IconButton, useRequest, useSnackbar } from '@centreon/ui';

import {
  labelSaveFilter,
  labelSaveAsNew,
  labelSave,
  labelFilterCreated,
  labelFilterSaved,
  labelEditFilters,
} from '../../translatedLabels';
<<<<<<< HEAD
import { listCustomFilters, updateFilter as updateFilterRequest } from '../api';
import { Filter } from '../models';
import {
  applyFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  editPanelOpenAtom,
  filtersDerivedAtom,
  sendingFilterAtom,
} from '../filterAtoms';
import { listCustomFiltersDecoder } from '../api/decoders';
=======
import { useResourceContext } from '../../Context';
import { updateFilter as updateFilterRequest } from '../api';
import { FilterState } from '../useFilter';
import memoizeComponent from '../../memoizedComponent';
import { Filter } from '../models';
>>>>>>> centreon/dev-21.10.x

import CreateFilterDialog from './CreateFilterDialog';

const areValuesEqual = pipe(symmetricDifference, isEmpty) as (a, b) => boolean;

const useStyles = makeStyles((theme) => ({
  save: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(2),
  },
}));

<<<<<<< HEAD
const SaveFilterMenu = (): JSX.Element => {
=======
type Props = Pick<
  FilterState,
  | 'currentFilter'
  | 'loadCustomFilters'
  | 'customFilters'
  | 'setEditPanelOpen'
  | 'filters'
  | 'appliedFilter'
  | 'search'
  | 'applyFilter'
>;

const SaveFilterMenuContent = ({
  currentFilter,
  applyFilter,
  loadCustomFilters,
  customFilters,
  setEditPanelOpen,
  filters,
}: Props): JSX.Element => {
>>>>>>> centreon/dev-21.10.x
  const classes = useStyles();

  const { t } = useTranslation();

<<<<<<< HEAD
  const [menuAnchor, setMenuAnchor] = useState<Element | null>(null);
  const [createFilterDialogOpen, setCreateFilterDialogOpen] = useState(false);

  const { sendRequest: sendListCustomFiltersRequest, sending } = useRequest({
    decoder: listCustomFiltersDecoder,
    request: listCustomFilters,
  });
=======
  const [menuAnchor, setMenuAnchor] = React.useState<Element | null>(null);
  const [createFilterDialogOpen, setCreateFilterDialogOpen] =
    React.useState(false);
>>>>>>> centreon/dev-21.10.x

  const {
    sendRequest: sendUpdateFilterRequest,
    sending: sendingUpdateFilterRequest,
  } = useRequest({
    request: updateFilterRequest,
  });

<<<<<<< HEAD
  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);
  const filters = useAtomValue(filtersDerivedAtom);
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);
  const setEditPanelOpen = useUpdateAtom(editPanelOpenAtom);
  const setSendingFilter = useUpdateAtom(sendingFilterAtom);

  const { showSuccessMessage } = useSnackbar();

  const openSaveFilterMenu = (event: MouseEvent<HTMLButtonElement>): void => {
=======
  const { showSuccessMessage } = useSnackbar();

  const openSaveFilterMenu = (event: React.MouseEvent): void => {
>>>>>>> centreon/dev-21.10.x
    setMenuAnchor(event.currentTarget);
  };

  const closeSaveFilterMenu = (): void => {
    setMenuAnchor(null);
  };

  const openCreateFilterDialog = (): void => {
    closeSaveFilterMenu();
    setCreateFilterDialogOpen(true);
  };

  const closeCreateFilterDialog = (): void => {
    setCreateFilterDialogOpen(false);
  };

<<<<<<< HEAD
  const loadCustomFilters = (): Promise<Array<Filter>> => {
    return sendListCustomFiltersRequest().then(({ result }) => {
      setCustomFilters(result.map(omit(['order'])));

      return result;
    });
  };

  const loadFiltersAndUpdateCurrent = (newFilter: Filter): void => {
    closeCreateFilterDialog();

    loadCustomFilters?.().then(() => {
=======
  const loadFiltersAndUpdateCurrent = (newFilter: Filter): void => {
    closeCreateFilterDialog();

    loadCustomFilters().then(() => {
>>>>>>> centreon/dev-21.10.x
      applyFilter(newFilter);
    });
  };

  const confirmCreateFilter = (newFilter: Filter): void => {
    showSuccessMessage(t(labelFilterCreated));

    loadFiltersAndUpdateCurrent(omit(['order'], newFilter));
  };

  const updateFilter = (): void => {
    sendUpdateFilterRequest({
      filter: omit(['id'], currentFilter),
      id: currentFilter.id,
    }).then((savedFilter) => {
      closeSaveFilterMenu();
      showSuccessMessage(t(labelFilterSaved));

      loadFiltersAndUpdateCurrent(omit(['order'], savedFilter));
    });
  };

  const openEditPanel = (): void => {
    setEditPanelOpen(true);
    closeSaveFilterMenu();
  };

  const isFilterDirty = (): boolean => {
    const retrievedFilter = find(propEq('id', currentFilter.id), filters);

    return !areValuesEqual(
      currentFilter.criterias,
      retrievedFilter?.criterias || [],
    );
  };

<<<<<<< HEAD
  useEffect(() => {
    setSendingFilter(sending);
  }, [sending]);

=======
>>>>>>> centreon/dev-21.10.x
  const isNewFilter = currentFilter.id === '';
  const canSaveFilter = and(isFilterDirty(), not(isNewFilter));
  const canSaveFilterAsNew = or(isFilterDirty(), isNewFilter);

  return (
    <>
      <IconButton
<<<<<<< HEAD
        aria-label={t(labelSaveFilter)}
        data-testid={labelSaveFilter}
        size="large"
=======
        data-testid={labelSaveFilter}
>>>>>>> centreon/dev-21.10.x
        title={t(labelSaveFilter)}
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
          disabled={!canSaveFilterAsNew}
          onClick={openCreateFilterDialog}
        >
          {t(labelSaveAsNew)}
        </MenuItem>
        <MenuItem disabled={!canSaveFilter} onClick={updateFilter}>
          <div className={classes.save}>
            <span>{t(labelSave)}</span>
            {sendingUpdateFilterRequest && <CircularProgress size={15} />}
          </div>
        </MenuItem>
        <MenuItem disabled={isEmpty(customFilters)} onClick={openEditPanel}>
          {t(labelEditFilters)}
        </MenuItem>
      </Menu>
      {createFilterDialogOpen && (
        <CreateFilterDialog
          open
          filter={currentFilter}
          onCancel={closeCreateFilterDialog}
          onCreate={confirmCreateFilter}
        />
      )}
    </>
  );
};

<<<<<<< HEAD
=======
const memoProps = [
  'updatedFilter',
  'customFilters',
  'appliedFilter',
  'filters',
  'currentFilter',
  'search',
];

const MemoizedSaveFilterMenuContent = memoizeComponent<Props>({
  Component: SaveFilterMenuContent,
  memoProps,
});

const SaveFilterMenu = (): JSX.Element => {
  const {
    filterWithParsedSearch,
    applyFilter,
    loadCustomFilters,
    customFilters,
    setEditPanelOpen,
    filters,
    appliedFilter,
    search,
  } = useResourceContext();

  return (
    <MemoizedSaveFilterMenuContent
      appliedFilter={appliedFilter}
      applyFilter={applyFilter}
      currentFilter={filterWithParsedSearch}
      customFilters={customFilters}
      filters={filters}
      loadCustomFilters={loadCustomFilters}
      search={search}
      setEditPanelOpen={setEditPanelOpen}
    />
  );
};

>>>>>>> centreon/dev-21.10.x
export default SaveFilterMenu;
