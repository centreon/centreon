import { useEffect, useMemo, useState } from 'react';

import { useAtom } from 'jotai';
import { isEmpty, isNil } from 'ramda';

import { getValue } from '@mui/system';

import {
  MultiAutocompleteField,
  SingleConnectedAutocompleteField
} from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import { BasicCriteria } from '../model';
import useInputData from '../useInputsData';
import { criteriaValueNameByIdAtom, searchAtom } from '../../filterAtoms';
import {
  findFieldInformationFromSearchInput,
  replaceValueFromSearchInput
} from '../utils';

const SelectInput = ({
  data,
  filterName,
  resourceType,
  changeCriteria
}): JSX.Element => {
  const [search, setSearch] = useAtom(searchAtom);

  const { target, valueSearchData } = useInputData({
    data,
    filterName,
    resourceType
  });

  const [value, setValue] = useState();

  const field = 'name';

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
      name: target.options?.find((item) => item.id === resourceType).name
    };
    const result = target.value?.filter((item) => item?.id !== resourceType);

    return [...result, value];
  };

  const handleSearchDataInSearchInput = (data) => {
    const values = data.values.map((item) => item?.value);
    if (!fieldData.target) {
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
      targetField: fieldData.target
    });
    setSearch(currentSearch);
  };

  const handleChange = (updatedValue) => {
    setValue({ id: updatedValue.id, name: updatedValue.name });
    const searchData = handleSearchData(updatedValue);
    const newValues = handleValues();

    handleSearchDataInSearchInput(searchData);
    changeCriteria({
      filterName,
      searchData,
      updatedValue: newValues
    });
  };

  const fieldData = useMemo((): { content: string; target: string } => {
    return findFieldInformationFromSearchInput({
      field: target?.searchData.field,
      search
    });
  }, [search, target]);

  const getEndpoint = ({ search, page }): string => {
    return buildResourcesEndpoint({
      limit: 10,
      page,
      resourceTypes: [resourceType],
      search
    });
  };

  const currentValue =
    !isEmpty(value) && !isNil(value)
      ? value
      : { id: valueSearchData?.valueId, name: valueSearchData?.value };

  return (
    <SingleConnectedAutocompleteField
      field={field}
      getEndpoint={getEndpoint}
      label={resourceType}
      placeholder={target?.label}
      value={currentValue}
      onChange={(_, updatedValue): void => handleChange(updatedValue)}
    />
  );
};

export default SelectInput;
