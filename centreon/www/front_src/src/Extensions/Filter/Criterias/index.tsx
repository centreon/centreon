// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import TuneIcon from '@mui/icons-material/Tune';
import { Grid } from '@mui/material';

import type { SelectEntry } from '@centreon/ui';
import { PopoverMenu, useMemoComponent } from '@centreon/ui';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, pipe, reject, sortBy } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { labelSearchOptions } from '../../translatedLabels';
import {
  applyCurrentFilterDerivedAtom,
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
    padding: theme.spacing(2),
    width: theme.spacing(30)
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

  return (
    <PopoverMenu
      icon={<TuneIcon fontSize="small" />}
      onClose={applyCurrentFilter}
      popperPlacement="bottom-start"
      title={t(labelSearchOptions)}
    >
      {(): JSX.Element => (
        <Grid
          alignItems="stretch"
          className={classes.container}
          container
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
