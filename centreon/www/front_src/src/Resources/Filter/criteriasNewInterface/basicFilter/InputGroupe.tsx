import { useState } from 'react';

import { equals, isNil } from 'ramda';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import useInputData from '../useInputsData';

const InputGroup = ({ data, filterName, changeCriteria, label }) => {
  const [currentValue, setCurrentValue] = useState();
  const { target } = useInputData({
    data,
    filterName
  });

  const getEndpoint = ({ search, page }): string =>
    target?.buildAutocompleteEndpoint({
      limit: 10,
      page,
      search
    });

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  const currentLabel = (label ?? target?.label) || '';

  const displayedColumn = currentLabel.includes('level') ? 'level' : '';

  const getUniqueOptions = (options) => {
    return [
      ...new Map(options.map((option) => [option.name, option])).values()
    ];
  };

  return (
    <div>
      {target && (
        <MultiConnectedAutocompleteField
          field="name"
          filterOptions={(options) => getUniqueOptions(options)}
          getEndpoint={getEndpoint}
          isOptionEqualToValue={isOptionEqualToValue}
          label={label ?? target?.label}
          labelKey={displayedColumn}
          placeholder={label ?? target?.label}
          search={target?.autocompleteSearch}
          value={currentValue || target?.value}
          onChange={(_, updatedValue): void => {
            setCurrentValue(updatedValue);
            changeCriteria({
              filterName,
              updatedValue
            });
          }}
        />
      )}
    </div>
  );
};

export default InputGroup;
