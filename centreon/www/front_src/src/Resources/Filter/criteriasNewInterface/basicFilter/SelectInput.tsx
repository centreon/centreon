import { useAtom } from 'jotai';
import { isEmpty } from 'ramda';

import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import getDefaultCriterias from '../../Criterias/default';
import { searchAtom } from '../../filterAtoms';
import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import useSearchInputDataByField from '../useSearchInputDataByField';
import { findData, replaceValueFromSearchInput } from '../utils';

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria
}): JSX.Element => {
  const field = 'name';

  const [search, setSearch] = useAtom(searchAtom);
  const { target, valueSearchData } = useInputData({
    data,
    filterName,
    resourceType
  });

  const { value, setValue } = useInputCurrentValues({
    content: { id: valueSearchData?.valueId, name: valueSearchData?.value },
    data: valueSearchData
  });

  const { content, fieldInformation } = useSearchInputDataByField({ field });

  const handleSearchData = (updatedValue) => {
    const { values } = target.searchData;
    const currentValue = {
      id: resourceType,
      value: updatedValue.name,
      valueId: updatedValue.id
    };

    const result = values?.filter((item) => item.id !== currentValue.id);
    if (values?.length <= 0) {
      return { ...target.searchData, values: [currentValue] };
    }

    return { ...target.searchData, values: [...result, currentValue] };
  };

  const handleValues = () => {
    const value = {
      id: resourceType,
      name: findData({
        data: target?.options,
        findBy: 'id',
        target: resourceType
      })?.name
    };
    const result = target.value?.filter((item) => item?.id !== resourceType);

    return [...result, value];
  };

  const handleSearchDataInSearchInput = (data) => {
    const values = data.values.map((item) => item?.value);
    if (!fieldInformation) {
      const currentSearch = search.concat(
        ' ',
        `${data.field}:${values.join()}`
      );
      setSearch(currentSearch);

      return;
    }

    const currentSearch = replaceValueFromSearchInput({
      newContent: `${field}:${values.join()}`,
      search,
      targetField: fieldInformation
    });
    setSearch(currentSearch);
  };

  const handleChange = (_, updatedValue) => {
    const searchData = handleSearchData(updatedValue);
    const newValues = handleValues();

    handleSearchDataInSearchInput(searchData);
    changeCriteria({
      filterName,
      searchData,
      updatedValue: newValues
    });
  };

  const onInputChange = (event, value) => {
    const initializedData = getDefaultCriterias().find(
      (item) => item.name === filterName
    );

    if (!event) {
      return;
    }
    if (value) {
      return;
    }

    const initializedTargetSearch = replaceValueFromSearchInput({
      newContent: '',
      search,
      targetField: fieldInformation
    });
    setSearch(initializedTargetSearch);
    setValue([]);
    changeCriteria({
      filterName,
      searchData: initializedData?.searchData,
      updatedValue: initializedData?.value
    });
  };

  const getEndpoint = ({ search, page }): string => {
    return buildResourcesEndpoint({
      limit: 10,
      page,
      resourceTypes: [resourceType],
      search
    });
  };

  return (
    <div>
      {target && !isEmpty(target) ? (
        <SingleConnectedAutocompleteField
          field="name"
          getEndpoint={getEndpoint}
          label={resourceType}
          placeholder={target?.label}
          value={value}
          onChange={(_, updatedValue): void => handleChange(_, updatedValue)}
          onInputChange={onInputChange}
        />
      ) : null}
    </div>
  );
};

export default SelectInput;
