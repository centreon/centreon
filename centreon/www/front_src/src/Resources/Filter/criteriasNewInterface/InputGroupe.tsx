import { MultiConnectedAutocompleteField } from '@centreon/ui';

const InputGroup = (data) => {
  const { baseEndpoint, getEndpoint } = data;

  return (
    <MultiConnectedAutocompleteField
      field="host.name"
      getEndpoint={(parameters): string => {
        return getEndpoint({ endpoint: baseEndpoint, parameters });
      }}
      label="Multi Connected Autocomplete"
      placeholder="Type here..."
    />
  );
};

export default InputGroup;
