import { equals, isNil } from 'ramda';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import useInputCurrentValues from '../useInputCurrentValues';
import useInputData from '../useInputsData';
import { removeDuplicateFromObjectArray } from '../utils';

const InputGroup = ({ data, filterName, changeCriteria, label }) => {
  const { target } = useInputData({
    data,
    filterName
  });

  const { value } = useInputCurrentValues({
    content: target?.value,
    data: target?.value
  });

  if (!target) {
    return null;
  }

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

  const getUniqueOptions = (options) =>
    removeDuplicateFromObjectArray({
      array: options,
      byFields: ['name']
    });

  const currentLabel = label || target?.label;

  const displayedColumn = currentLabel.includes('level') ? 'level' : '';

  return (
    <MultiConnectedAutocompleteField
      field="name"
      filterOptions={getUniqueOptions}
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={currentLabel}
      labelKey={displayedColumn}
      placeholder={currentLabel}
      search={target?.autocompleteSearch}
      value={value}
      onChange={(_, updatedValue): void => {
        changeCriteria({
          filterName,
          updatedValue
        });
      }}
    />
  );
};

export default InputGroup;
