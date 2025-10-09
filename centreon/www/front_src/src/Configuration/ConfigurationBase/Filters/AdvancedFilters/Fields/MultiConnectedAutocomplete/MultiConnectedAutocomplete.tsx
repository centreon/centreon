import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField } from '@centreon/ui';
import useMultiConnectedAutocomplete from './useMultiConnectedAutocomplete';

const MultiConnectedAutocomplete = ({
  name,
  label,
  getEndpoint,
  setFilters,
  filters
}): JSX.Element => {
  const { t } = useTranslation();

  const { isOptionEqualToValue, deleteItem, change, value } =
    useMultiConnectedAutocomplete({
      name,
      setFilters,
      filters
    });

  return (
    <MultiConnectedAutocompleteField
      disableClearable={false}
      disableSortedOptions
      chipProps={{
        color: 'primary',
        onDelete: deleteItem(name)
      }}
      dataTestId={label}
      field="name"
      getEndpoint={getEndpoint}
      isOptionEqualToValue={isOptionEqualToValue}
      label={t(label)}
      value={value}
      onChange={change}
    />
  );
};

export default MultiConnectedAutocomplete;
