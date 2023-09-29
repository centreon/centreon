import { useState } from 'react';

import { useSetAtom } from 'jotai';

import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';

import { Criteria, CriteriaById } from '../Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../filterAtoms';

import BasicFilter from './basicFilter';
import InputGroup from './basicFilter/InputGroup';
import SectionWrapper from './basicFilter/sections';
import ExtendedFilter from './extendedFilter';
import { BasicCriteria, CategoryFilter, ExtendedCriteria } from './model';
import { handleDataByCategoryFilter, mergeArraysByField } from './utils';
import { CheckBoxWrapper } from './CheckBoxWrapper';

export { CheckboxGroup } from '@centreon/ui';

const CriteriasNewInterface = ({ data }): JSX.Element => {
  const [open, setOpen] = useState(false);

  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const { newSelectableCriterias: buildCriterias, selectableCriterias } = data;

  const changeCriteria = ({ updatedValue, filterName, searchData }): void => {
    const parameters = {
      name: filterName,
      value: updatedValue
    };

    setCriteriaAndNewFilter(
      searchData ? { ...parameters, searchData } : parameters
    );
  };

  const controlFilterInterface = (event): void => setOpen(event.target.checked);

  const getDataByCategoryFilter = ({
    CriteriaType,
    selectableCriteriass,
    buildCriteriass
  }): Array<(CriteriaById & Criteria) | Criteria> => {
    const dataInteraction = selectableCriteriass.filter((item) =>
      Object.values(CriteriaType).includes(item.name)
    );

    const dataOfBuild = Object.keys(buildCriteriass).map((item) => {
      if (!Object.values(CriteriaType).includes(item)) {
        return null;
      }

      return { ...buildCriterias[item], name: item };
    });

    return mergeArraysByField({
      firstArray: dataInteraction,
      mergeBy: 'name',
      secondArray: dataOfBuild
    });
  };

  const getData = (categoryFilter, select, build) => {
    const criteriaType =
      categoryFilter === CategoryFilter.BasicFilter
        ? Object.values(BasicCriteria)
        : Object.values(ExtendedCriteria);
    const dataByCategory = getDataByCategoryFilter({
      CriteriaType: criteriaType,
      buildCriteriass: build,
      selectableCriteriass: select
    });

    return handleDataByCategoryFilter({
      data: dataByCategory,
      fieldToUpdate: 'options',
      filter: categoryFilter
    });
  };

  const basicData = getData(
    CategoryFilter.BasicFilter,
    selectableCriterias,
    buildCriterias
  );

  const extendedData = getData(
    CategoryFilter.ExtendedFilter,
    selectableCriterias,
    buildCriterias
  );

  return (
    <>
      <div style={{ display: 'flex', flexDirection: 'row' }}>
        <BasicFilter
          poller={
            <InputGroup
              changeCriteria={changeCriteria}
              data={basicData}
              filterName={BasicCriteria.monitoringServers}
              label="Poller"
            />
          }
          sections={
            <SectionWrapper
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
          state={
            <CheckBoxWrapper
              changeCriteria={changeCriteria}
              data={basicData}
              filterName={BasicCriteria.states}
              title="State"
            />
          }
        />
        {open && (
          <ExtendedFilter changeCriteria={changeCriteria} data={extendedData} />
        )}
      </div>
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
    </>
  );
};

export default CriteriasNewInterface;
