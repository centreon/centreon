import { MultiAutocompleteField } from '@centreon/ui';

import { SetStateAction } from 'jotai';
import { Dispatch, JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { NamedEntity } from '../../../../../models';
import useMultiAutocomplete from './useMultiAutocomplete';

interface Props<TFilters> {
  label: string;
  name: string;
  options: Array<NamedEntity>;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const MultiAutocomplete = <TFilters,>({
  label,
  name,
  options,
  filters,
  setFilters
}: Props<TFilters>): JSX.Element => {
  const { t } = useTranslation();

  const { value, change, deleteItem } = useMultiAutocomplete<TFilters>({
    filters,
    name,
    setFilters
  });

  return (
    <MultiAutocompleteField
      chipProps={{
        color: 'primary',
        onDelete: deleteItem(name)
      }}
      dataTestId={label}
      disableSortedOptions
      label={t(label)}
      onChange={change}
      options={options}
      value={value}
    />
  );
};

export default MultiAutocomplete;
