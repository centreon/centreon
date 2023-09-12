import { useState } from 'react';

import { pipe, isNil, sortBy, reject } from 'ramda';
import { useAtomValue } from 'jotai';

import Paper from '@mui/material/Paper';
import Switch from '@mui/material/Switch';

import useFilterByModule from '../useFilterByModule';
import { filterByInstalledModulesWithParsedSearchDerivedAtom } from '../filterAtoms';
import {
  CriteriaDisplayProps,
  Criteria as CriteriaModel
} from '../Criterias/models';
import { criteriaNameSortOrder } from '../Criterias/searchQueryLanguage/models';

import Actions from './Actions';
import BasicFilter from './BasicFilter';
import ExtendedFilter from './ExtendedFilter';

export { CheckboxGroup } from '@centreon/ui';

const CriteriasNewInterface = (): JSX.Element => {
  const [open, setOpen] = useState(false);
  const resourcesType = [];

  const { newCriteriaValueName, newSelectableCriterias } = useFilterByModule();

  const filterByInstalledModulesWithParsedSearch = useAtomValue(
    filterByInstalledModulesWithParsedSearchDerivedAtom
  );

  const getSelectableCriteriaByName = (name: string): CriteriaDisplayProps =>
    newSelectableCriterias[name];

  const isNonSelectableCriteria = (criteria: CriteriaModel): boolean =>
    pipe(({ name }) => name, getSelectableCriteriaByName, isNil)(criteria);

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

  const controlFilterInterface = (event): void => setOpen(event.target.checked);

  return (
    <Paper>
      <BasicFilter resourcesType={resourcesType} />
      {open && <ExtendedFilter />}
      <Switch
        checked={open}
        inputProps={{ 'aria-label': 'controlled' }}
        onChange={controlFilterInterface}
      />
      <Actions />
    </Paper>
  );
};

export default CriteriasNewInterface;
