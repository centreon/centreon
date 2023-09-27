import { useEffect, useMemo, useState } from 'react';

import { useAtom } from 'jotai';
import { isEmpty } from 'ramda';

import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { buildResourcesEndpoint } from '../../../Listing/api/endpoint';
import getDefaultCriterias from '../../Criterias/default';
import { searchAtom } from '../../filterAtoms';
import useInputData from '../useInputsData';
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
  const { target, valueSearchData } = useInputData({
    data,
    filterName,
    resourceType
  });

  const [value, setValue] = useState([]);
  const [search, setSearch] = useAtom(searchAtom);

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
    setSearch('');
    setValue([]);
    changeCriteria({
      filterName,
      searchData: initializedData?.searchData,
      updatedValue: initializedData?.value
    });
  };
  useEffect(() => {
    if (!valueSearchData) {
      setValue([]);

      return;
    }
    setValue({ id: valueSearchData.valueId, name: valueSearchData.value });
  }, [valueSearchData]);

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
          onInputChange={(event, value) => onInputChange(event, value)}
        />
      ) : null}
    </div>
  );
};

export default SelectInput;
