import { isNil, equals } from 'ramda';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import useInputData from '../useInputsData';

const InputGroup = ({ data, filterName, changeCriteria, label }) => {
  const { options, values, target } = useInputData({
    data,
    filterName
  });

  const getEndpoint = ({ search, page }): string =>
    target?.buildAutocompleteEndpoint({
      limit: 10,
      page,
      search
    });

  return (
    <div>
      {target && (
        <MultiConnectedAutocompleteField
          field="name"
          getEndpoint={getEndpoint}
          label={label ?? target?.label}
          placeholder={label ?? target?.label}
          search={target?.autocompleteSearch}
          onChange={(_, updatedValue): void => {
            changeCriteria({ filterName, updatedValue });
          }}
        />
      )}
    </div>
  );
};

export default InputGroup;
