import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { findData, removeDuplicateFromObjectArray } from '../utils';

import useSectionsData from './sections/useSections';

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria
}): JSX.Element => {
  const { sectionData } = useSectionsData({ data, sectionType: resourceType });
  const { target, valueSearchData } = useInputData({
    data: sectionData,
    filterName,
    resourceType
  });

  const { value, setValue } = useInputCurrentValues({
    content: { id: valueSearchData?.valueId, name: valueSearchData?.value },
    data: valueSearchData
  });

  const handleSearchData = (updatedValue) => {
    const { values } = target?.searchData;
    const currentValue = {
      id: resourceType,
      value: updatedValue.name,
      valueId: updatedValue.id
    };

    const searchedValues = removeDuplicateFromObjectArray({
      array: [...values, currentValue],
      byFields: ['id']
    });

    return { ...target.searchData, values: searchedValues };
  };

  const handleValues = () => {
    const selectedValue = {
      id: resourceType,
      name: findData({
        data: target?.options,
        findBy: 'id',
        target: resourceType
      })?.name
    };

    return removeDuplicateFromObjectArray({
      array: [...target.value, selectedValue],
      byFields: ['id']
    });
  };

  const handleChange = (updatedValue) => {
    const searchData = handleSearchData(updatedValue);
    const selectedValues = handleValues();
    changeCriteria({
      filterName,
      searchData,
      updatedValue: selectedValues
    });
  };

  const initializeInput = () => {
    const initializedSearchedData = target?.searchData?.values.filter(
      (item) => item?.id !== resourceType
    );

    const updatedValue = target?.value?.filter(
      (item) => item?.id !== resourceType
    );

    setValue([]);
    changeCriteria({
      filterName,
      searchData: { ...target?.searchData, values: initializedSearchedData },
      updatedValue
    });
  };

  const onInputChange = (event, value) => {
    if (!event) {
      return;
    }
    if (value) {
      return;
    }

    initializeInput();
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
    <SingleConnectedAutocompleteField
      field="name"
      getEndpoint={getEndpoint}
      label={resourceType}
      placeholder={target?.label}
      value={value}
      onChange={(_, updatedValue): void => handleChange(updatedValue)}
      onInputChange={onInputChange}
    />
  );
};

export default SelectInput;
