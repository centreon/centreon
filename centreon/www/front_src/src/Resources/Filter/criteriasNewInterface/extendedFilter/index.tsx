import { forwardRef } from 'react';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { ChangedCriteriaParams, ExtendedCriteria } from '../model';

import FilterSearch from './FilterSearch';
import MemoizedCheckBoxWrapper from './MemoizedCheckBoxWrapper';
import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import useExtendedFilter from './useExtendedFilter';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
}

const ExtendedFilter = forwardRef(
  ({ data, changeCriteria }: Props, ref): JSX.Element => {
    const { resourceTypes, inputGroupsData, statusTypes } = useExtendedFilter({
      data
    });

    return (
      <div ref={ref}>
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
  }
);

export default ExtendedFilter;
