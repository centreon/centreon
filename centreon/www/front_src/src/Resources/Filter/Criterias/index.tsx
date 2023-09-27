import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { pipe, isNil, sortBy, reject } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button, Grid } from '@mui/material';
import TuneIcon from '@mui/icons-material/Tune';

import { PopoverMenu, useMemoComponent } from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import { hoveredNavigationItemsAtom } from '../../../Navigation/Sidebar/sideBarAtoms';
import {
  labelClear,
  labelSearch,
  labelSearchOptions
} from '../../translatedLabels';
import {
  applyCurrentFilterDerivedAtom,
  clearFilterDerivedAtom,
  filterWithParsedSearchDerivedAtom,
  filterByInstalledModulesWithParsedSearchDerivedAtom,
  currentFilterAtom
} from '../filterAtoms';
import useFilterByModule from '../useFilterByModule';
import CriteriasNewInterface from '../criteriasNewInterface';
import CreateFilterDialog from '../Save/CreateFilterDialog';
import { selectedStatusByResourceTypeAtom } from '../criteriasNewInterface/basicFilter/atoms';

import Criteria from './Criteria';
import { CriteriaDisplayProps, Criteria as CriteriaModel } from './models';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';

const useStyles = makeStyles()((theme) => ({
  container: {
    padding: theme.spacing(2)
  },
  searchButton: {
    marginTop: theme.spacing(1)
  }
}));

const CriteriasContent = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [isCreateFilterDialogOpen, setIsCreateFilterDialogOpen] =
    useState(false);
  const currentFilter = useAtomValue(currentFilterAtom);
  const hoveredNavigationItem = useAtomValue(hoveredNavigationItemsAtom);
  const canOpenPopover = isNil(hoveredNavigationItem);

  const { newCriteriaValueName, newSelectableCriterias } = useFilterByModule();

  const filterByInstalledModulesWithParsedSearch = useAtomValue(
    filterByInstalledModulesWithParsedSearchDerivedAtom
  );

  const getSelectableCriterias = (): Array<CriteriaModel> => {
    const criteriasValue = filterByInstalledModulesWithParsedSearch({
      criteriaName: newCriteriaValueName
    });

    const criterias = sortBy(
      ({ name }) => criteriaNameSortOrder[name],
      criteriasValue.criterias
    );

    return reject(isNonSelectableCriteria)(criterias);
  };

  const getSelectableCriteriaByName = (name: string): CriteriaDisplayProps =>
    newSelectableCriterias[name];

  const isNonSelectableCriteria = (criteria: CriteriaModel): boolean =>
    pipe(({ name }) => name, getSelectableCriteriaByName, isNil)(criteria);

  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const setSelectedStatusByResourceType = useSetAtom(
    selectedStatusByResourceTypeAtom
  );
  const clearFilter = useSetAtom(clearFilterDerivedAtom);

  const canSaveFilterAsNew = true;

  const clearFilters = () => {
    clearFilter();
    setSelectedStatusByResourceType(null);
  };

  return (
    <PopoverMenu
      canOpen={canOpenPopover}
      icon={<TuneIcon fontSize="small" />}
      popperPlacement="bottom-start"
      title={t(labelSearchOptions) as string}
      onClose={applyCurrentFilter}
    >
      {(): JSX.Element => (
        <Grid
          container
          alignItems="stretch"
          className={classes.container}
          direction="column"
          spacing={1}
        >
          <CriteriasNewInterface
            data={{
              newSelectableCriterias,
              selectableCriterias: getSelectableCriterias()
            }}
          />
          {/* {getSelectableCriterias().map(({ name, value }) => {
            return (
              <Grid item key={name}>
                <Criteria name={name} value={value as Array<SelectEntry>} />
              </Grid>
            );
          })} */}
          <Grid container item className={classes.searchButton} spacing={1}>
            <Grid item data-testid={labelClear}>
              <Button
                color="primary"
                data-testid="Filter Clear"
                size="small"
                onClick={clearFilters}
              >
                {t(labelClear)}
              </Button>
            </Grid>
            <Grid item data-testid={labelSearch}>
              <Button
                color="primary"
                data-testid="Filter Search"
                size="small"
                variant="contained"
                onClick={applyCurrentFilter}
              >
                {t(labelSearch)}
              </Button>
            </Grid>
            <Grid item data-testid="save">
              <Button
                color="primary"
                data-testid="save new Filter"
                size="small"
                variant="contained"
                onClick={() => console.log('saaaave')}
              >
                Save
              </Button>
            </Grid>
            {/* {canSaveFilterAsNew && isCreateFilterDialogOpen && (
              <CreateFilterDialog
                filter={currentFilter}
                open={isCreateFilterDialogOpen}
                onCancel={() => {
                  setIsCreateFilterDialogOpen(false);
                }}
                onCreate={confirmCreateFilter}
              />
            )} */}
          </Grid>
        </Grid>
      )}
    </PopoverMenu>
  );
};

const Criterias = (): JSX.Element => {
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );

  return useMemoComponent({
    Component: <CriteriasContent />,
    memoProps: [filterWithParsedSearch]
  });
};

export default Criterias;
