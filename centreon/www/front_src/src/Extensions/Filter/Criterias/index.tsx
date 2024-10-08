import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, pipe, reject, sortBy } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import TuneIcon from '@mui/icons-material/Tune';
import { Button, Grid } from '@mui/material';

import { PopoverMenu, useMemoComponent } from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import {
  labelClear,
  labelSearch,
  labelSearchOptions
} from '../../translatedLabels';
import {
  applyCurrentFilterDerivedAtom,
  clearFilterDerivedAtom,
  filterWithParsedSearchDerivedAtom
} from '../filterAtoms';

import Criteria from './Criteria';
import {
  CriteriaDisplayProps,
  Criteria as CriteriaModel,
  selectableCriterias
} from './models';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';

const useStyles = makeStyles()((theme) => ({
  container: {
    padding: theme.spacing(2)
  },
  searchButton: {
    marginTop: theme.spacing(1)
  }
}));

const getSelectableCriteriaByName = (name: string): CriteriaDisplayProps =>
  selectableCriterias[name];

const isNonSelectableCriteria = (criteria: CriteriaModel): boolean =>
  pipe(({ name }) => name, getSelectableCriteriaByName, isNil)(criteria);

const CriteriasContent = (): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );

  const getSelectableCriterias = (): Array<CriteriaModel> => {
    const criterias = sortBy(
      ({ name }) => criteriaNameSortOrder[name],
      filterWithParsedSearch
    );

    return reject(isNonSelectableCriteria)(criterias);
  };

  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const clearFilter = useSetAtom(clearFilterDerivedAtom);

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
          {getSelectableCriterias().map(({ name, value }) => {
            return (
              <Grid item key={name}>
                <Criteria name={name} value={value as Array<SelectEntry>} />
              </Grid>
            );
          })}
          <Grid container item className={classes.searchButton} spacing={1}>
            <Grid item>
              <Button color="primary" size="small" onClick={clearFilter}>
                {t(labelClear)}
              </Button>
            </Grid>
            <Grid item>
              <Button
                color="primary"
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
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );

  return useMemoComponent({
    Component: <CriteriasContent />,
    memoProps: [filterWithParsedSearch]
  });
};

export default Criterias;
