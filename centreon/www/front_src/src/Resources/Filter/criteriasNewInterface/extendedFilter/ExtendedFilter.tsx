import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { CheckBoxWrapper } from '../basicFilter/CheckBox';
import InputGroup from '../basicFilter/InputGroupe';
import SelectInput from '../basicFilter/SelectInput';
import { ExtendedCriteria } from '../model';

import FilterSearch from './FilterSearch/FilterSearch';

const ExtendedFilter = ({ data, changeCriteria }): JSX.Element => {
  const resourcesType = data?.find(
    (item) => item.name === ExtendedCriteria.resourceTypes
  );

  const statusType = data?.filter(
    (item) => item.name === ExtendedCriteria.statusTypes
  );

  return (
    <div style={{ minWidth: 400 }}>
      {data.map((item) => {
        return (
          item?.buildAutocompleteEndpoint && (
            <InputGroup
              changeCriteria={changeCriteria}
              data={data}
              filterName={item.name}
            />
          )
        );
      })}
      {resourcesType.options.map((item, index) => (
        <SelectInput
          changeCriteria={changeCriteria}
          data={data}
          filterName={ExtendedCriteria.resourceTypes}
          key={index}
          sectionType={item.id}
        />
      ))}

      <FilterSearch
        field={SearchableFields.information}
        placeHolder="Information"
      />
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={statusType}
        filterName={ExtendedCriteria.statusTypes}
        title="Status type"
      />
    </div>
  );
};

export default ExtendedFilter;
