import { useMemoComponent } from '@centreon/ui';

import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { CheckBoxWrapper } from '../CheckBoxWrapper';
import InputGroup from '../basicFilter/InputGroup';
import SelectInput from '../basicFilter/SelectInput';
import { ExtendedCriteria } from '../model';
import { findData } from '../utils';

import FilterSearch from './FilterSearch';
import useExtendedFilter from './useExtendedFilter';

const ExtendedFilter = ({ data, changeCriteria }): JSX.Element => {
  const { resourceTypes, inputGroupsData, statusTypes } = useExtendedFilter({
    data
  });

  return (
    <div style={{ minWidth: 400 }}>
      {inputGroupsData?.map((item) => (
        <MemoizedInputGroup
          changeCriteria={changeCriteria}
          data={data}
          filterName={item.name}
          key={item.name}
        />
      ))}
      {resourceTypes?.map((item) => (
        <MemoizedSelectInput
          changeCriteria={changeCriteria}
          data={data}
          filterName={ExtendedCriteria.resourceTypes}
          key={item.name}
          resourceType={item.id}
        />
      ))}

      <FilterSearch
        field={SearchableFields.information}
        placeholder="Information"
      />
      <MemoizedCheckBoxWrapper
        changeCriteria={changeCriteria}
        data={statusTypes}
      />
    </div>
  );
};

export const MemoizedCheckBoxWrapper = ({
  changeCriteria,
  data
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={data}
        filterName={ExtendedCriteria.statusTypes}
        title="Status type"
      />
    ),

    memoProps: [
      findData({
        data,
        target: ExtendedCriteria.statusTypes
      })?.value
    ]
  });
};

const MemoizedSelectInput = ({
  basicData,
  changeCriteria,
  filterName,
  resourceType
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        resourceType={resourceType}
      />
    ),
    memoProps: [
      findData({ data: basicData, target: filterName })?.value,
      findData({ data: basicData, target: filterName })?.searchData?.values
    ]
  });
};

const MemoizedInputGroup = ({
  data,
  changeCriteria,
  filterName
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
      />
    ),
    memoProps: [findData({ data, target: filterName })?.value]
  });
};

export default ExtendedFilter;
