import { forwardRef } from 'react';

import { useAtomValue } from 'jotai';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { ChangedCriteriaParams, ExtendedCriteria } from '../model';
import { displayInformationFilterAtom } from '../basicFilter/atoms';

import FilterSearch from './FilterSearch';
import MemoizedCheckBoxWrapper from './MemoizedCheckBoxWrapper';
import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import useExtendedFilter from './useExtendedFilter';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
}

const ExtendedFilter = ({ data, changeCriteria }: Props): JSX.Element => {
  const { resourceTypes, inputGroupsData, statusTypes } = useExtendedFilter({
    data
  });

  const displayInformationFilter = useAtomValue(displayInformationFilterAtom);

  return (
    <div style={{ width: 300 }}>
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

      {displayInformationFilter && (
        <FilterSearch
          field={SearchableFields.information}
          placeholder="Information"
        />
      )}
      {displayInformationFilter && (
        <MemoizedCheckBoxWrapper
          changeCriteria={changeCriteria}
          data={statusTypes}
        />
      )}
    </div>
  );
};

export default ExtendedFilter;
