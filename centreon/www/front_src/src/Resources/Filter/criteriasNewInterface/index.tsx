import { useMemo, useRef, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import FormControlLabel from '@mui/material/FormControlLabel';
import Slide from '@mui/material/Slide';
import Switch from '@mui/material/Switch';

import { Criteria, CriteriaDisplayProps } from '../Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../filterAtoms';

import MemoizedPoller from './MemoizedPoller';
import MemoizedState from './MemoizedState';
import BasicFilter from './basicFilter';
import { displayActionsAtom } from './basicFilter/atoms';
import SectionWrapper from './basicFilter/sections';
import ExtendedFilter from './extendedFilter';
import {
  BasicCriteria,
  BuildDataByCategoryFilter,
  CategoryFilter,
  ChangedCriteriaParams,
  Data,
  DataByCategoryFilter,
  ExtendedCriteria
} from './model';
import useSearchWihSearchDataCriteria from './useSearchWithSearchDataCriteria';
import { handleDataByCategoryFilter, mergeArraysByField } from './utils';

export { CheckboxGroup } from '@centreon/ui';

interface Criterias {
  actions: JSX.Element;
  data: Data;
}

const CriteriasNewInterface = ({ data, actions }: Criterias): JSX.Element => {
  const containerRef = useRef(null);
  const [open, setOpen] = useState(false);

  const displayActions = useAtomValue(displayActionsAtom);

  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const { newSelectableCriterias: buildCriterias, selectableCriterias } = data;

  useSearchWihSearchDataCriteria({ selectableCriterias });

  const changeCriteria = ({
    updatedValue,
    filterName,
    searchData
  }: ChangedCriteriaParams): void => {
    const parameters = {
      name: filterName,
      value: updatedValue
    };

    setCriteriaAndNewFilter(
      searchData ? { ...parameters, searchData } : parameters
    );
  };

  const controlFilterInterface = (event): void => setOpen(event.target.checked);

  const buildDataByCategoryFilter = ({
    CriteriaType,
    selectableCriteria,
    buildedCriteria
  }: BuildDataByCategoryFilter): Array<CriteriaDisplayProps & Criteria> => {
    const dataInteraction = selectableCriteria.filter((item) =>
      Object.values(CriteriaType).includes(item.name)
    );

    const dataOfBuild = Object.keys(buildedCriteria).map((item) => {
      if (!Object.values(CriteriaType).includes(item)) {
        return null;
      }

      return { ...buildCriterias[item], name: item };
    });

    return mergeArraysByField({
      firstArray: dataInteraction,
      mergeBy: 'name',
      secondArray: dataOfBuild
    }) as Array<CriteriaDisplayProps & Criteria>;
  };

  const getDataByCategoryFilter = ({
    categoryFilter,
    selectableCriteria,
    buildedCriteria
  }: DataByCategoryFilter): Array<Criteria & CriteriaDisplayProps> => {
    const criteriaType =
      categoryFilter === CategoryFilter.BasicFilter
        ? Object.values(BasicCriteria)
        : Object.values(ExtendedCriteria);
    const dataByCategory = buildDataByCategoryFilter({
      CriteriaType: criteriaType,
      buildedCriteria,
      selectableCriteria
    });

    return handleDataByCategoryFilter({
      data: dataByCategory,
      fieldToUpdate: 'options',
      filter: categoryFilter
    });
  };

  const basicData = useMemo(() => {
    return getDataByCategoryFilter({
      buildedCriteria: buildCriterias,
      categoryFilter: CategoryFilter.BasicFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  const extendedData = useMemo(() => {
    return getDataByCategoryFilter({
      buildedCriteria: buildCriterias,
      categoryFilter: CategoryFilter.ExtendedFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  return (
    <>
      <div ref={containerRef} style={{ display: 'flex', flexDirection: 'row' }}>
        <BasicFilter
          poller={
            <MemoizedPoller
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
          sections={
            <SectionWrapper
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
          state={
            <MemoizedState
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
        />

        {open && (
          <Slide direction="right" in={open}>
            <ExtendedFilter
              changeCriteria={changeCriteria}
              data={extendedData}
            />
          </Slide>
        )}
      </div>

      {displayActions && (
        <>
          <FormControlLabel
            control={
              <Switch
                checked={open}
                inputProps={{ 'aria-label': 'controlled' }}
                onChange={controlFilterInterface}
              />
            }
            label="Advanced mode"
          />
          {actions}
        </>
      )}
    </>
  );
};

export default CriteriasNewInterface;
