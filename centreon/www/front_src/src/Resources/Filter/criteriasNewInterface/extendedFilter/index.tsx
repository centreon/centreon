import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { CheckBoxWrapper } from '../CheckBoxWrapper';
import InputGroup from '../basicFilter/InputGroup';
import SelectInput from '../basicFilter/SelectInput';
import { ExtendedCriteria } from '../model';

import FilterSearch from './FilterSearch';
import useExtendedFilter from './useExtendedFilter';

const ExtendedFilter = ({ data, changeCriteria }): JSX.Element => {
  const { resourceTypes, inputGroupsData, statusTypes } = useExtendedFilter({
    data
  });

  return (
    <div style={{ minWidth: 400 }}>
      {inputGroupsData?.map((item) => (
        <InputGroup
          changeCriteria={changeCriteria}
          data={data}
          filterName={item.name}
          key={item.name}
        />
      ))}
      {resourceTypes?.map((item) => (
        <SelectInput
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
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={statusTypes}
        filterName={ExtendedCriteria.statusTypes}
        title="Status type"
      />
    </div>
  );
};

export default ExtendedFilter;
