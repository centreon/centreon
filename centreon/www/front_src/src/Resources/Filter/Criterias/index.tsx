<<<<<<< HEAD
import { useTranslation } from 'react-i18next';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { pipe, isNil, sortBy, reject } from 'ramda';

import { Button, Grid } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import TuneIcon from '@mui/icons-material/Tune';

import { PopoverMenu, SelectEntry, useMemoComponent } from '@centreon/ui';

=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { Button, Grid, makeStyles } from '@material-ui/core';
import TuneIcon from '@material-ui/icons/Tune';

import { PopoverMenu, SelectEntry, useMemoComponent } from '@centreon/ui';

import { useResourceContext } from '../../Context';
>>>>>>> centreon/dev-21.10.x
import {
  labelClear,
  labelSearch,
  labelSearchOptions,
} from '../../translatedLabels';
<<<<<<< HEAD
import {
  applyCurrentFilterDerivedAtom,
  clearFilterDerivedAtom,
  filterWithParsedSearchDerivedAtom,
} from '../filterAtoms';

import Criteria from './Criteria';
import {
  CriteriaDisplayProps,
  selectableCriterias,
  Criteria as CriteriaModel,
} from './models';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';
=======
import { FilterState } from '../useFilter';

import Criteria from './Criteria';
import { Criteria as CriteriaInterface } from './models';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  container: {
    padding: theme.spacing(2),
  },
  searchButton: {
    marginTop: theme.spacing(1),
  },
}));

<<<<<<< HEAD
const getSelectableCriteriaByName = (name: string): CriteriaDisplayProps =>
  selectableCriterias[name];

const isNonSelectableCriteria = (criteria: CriteriaModel): boolean =>
  pipe(({ name }) => name, getSelectableCriteriaByName, isNil)(criteria);

const CriteriasContent = (): JSX.Element => {
=======
interface Props
  extends Pick<FilterState, 'applyCurrentFilter' | 'clearFilter'> {
  criterias: Array<CriteriaInterface>;
}

const CriteriasContent = ({
  criterias,
  applyCurrentFilter,
  clearFilter,
}: Props): JSX.Element => {
>>>>>>> centreon/dev-21.10.x
  const classes = useStyles();

  const { t } = useTranslation();

<<<<<<< HEAD
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom,
  );

  const getSelectableCriterias = (): Array<CriteriaModel> => {
    const criterias = sortBy(
      ({ name }) => criteriaNameSortOrder[name],
      filterWithParsedSearch.criterias,
    );

    return reject(isNonSelectableCriteria)(criterias);
  };

  const applyCurrentFilter = useUpdateAtom(applyCurrentFilterDerivedAtom);
  const clearFilter = useUpdateAtom(clearFilterDerivedAtom);

=======
>>>>>>> centreon/dev-21.10.x
  return (
    <PopoverMenu
      icon={<TuneIcon fontSize="small" />}
      popperPlacement="bottom-start"
      title={t(labelSearchOptions)}
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
<<<<<<< HEAD
          {getSelectableCriterias().map(({ name, value }) => {
=======
          {criterias.map(({ name, value }) => {
>>>>>>> centreon/dev-21.10.x
            return (
              <Grid item key={name}>
                <Criteria name={name} value={value as Array<SelectEntry>} />
              </Grid>
            );
          })}
          <Grid container item className={classes.searchButton} spacing={1}>
            <Grid item data-testid={labelClear}>
              <Button color="primary" size="small" onClick={clearFilter}>
                {t(labelClear)}
              </Button>
            </Grid>
<<<<<<< HEAD
            <Grid item data-testid={labelSearch}>
              <Button
                color="primary"
=======
            <Grid item>
              <Button
                color="primary"
                data-testid={labelSearch}
>>>>>>> centreon/dev-21.10.x
                size="small"
                variant="contained"
                onClick={applyCurrentFilter}
              >
                {t(labelSearch)}
              </Button>
            </Grid>
          </Grid>
        </Grid>
      )}
    </PopoverMenu>
  );
};

const Criterias = (): JSX.Element => {
<<<<<<< HEAD
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom,
  );

  return useMemoComponent({
    Component: <CriteriasContent />,
    memoProps: [filterWithParsedSearch],
=======
  const {
    getMultiSelectCriterias,
    applyCurrentFilter,
    clearFilter,
    filterWithParsedSearch,
  } = useResourceContext();

  const criterias = getMultiSelectCriterias();

  return useMemoComponent({
    Component: (
      <CriteriasContent
        applyCurrentFilter={applyCurrentFilter}
        clearFilter={clearFilter}
        criterias={criterias}
      />
    ),
    memoProps: [criterias, filterWithParsedSearch],
>>>>>>> centreon/dev-21.10.x
  });
};

export default Criterias;
