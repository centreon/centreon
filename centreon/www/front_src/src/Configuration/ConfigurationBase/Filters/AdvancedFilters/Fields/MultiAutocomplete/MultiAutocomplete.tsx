import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { MultiAutocompleteField } from '@centreon/ui';
import { NamedEntity } from '../../../../../models';
import useMultiAutocomplete from './useMultiAutocomplete';

interface Props {
  label: string;
  name: string;
  options: Array<NamedEntity>;
}

const MultiAutocomplete = ({ label, name, options }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { value, change, deleteItem } = useMultiAutocomplete({ name });

  return (
    <MultiAutocompleteField
      disableSortedOptions
      chipProps={{
        color: 'primary',
        onDelete: deleteItem(name)
      }}
      dataTestId={label}
      label={t(label)}
      options={options}
      value={value}
      onChange={change}
    />
  );
};

export default MultiAutocomplete;
