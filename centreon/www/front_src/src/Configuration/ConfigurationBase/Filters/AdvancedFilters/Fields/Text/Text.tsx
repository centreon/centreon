import { TextField } from '@centreon/ui';
import { SetStateAction } from 'jotai';
import { Dispatch } from 'react';
import { useTranslation } from 'react-i18next';
import useText from './useText';

interface Props<TFilters> {
  name: string;
  label: string;
  filters: TFilters;
  setFilters: Dispatch<SetStateAction<TFilters>>;
}

const Text = <TFilters,>({
  label,
  name,
  filters,
  setFilters
}: Props<TFilters>) => {
  const { t } = useTranslation();

  const { change, value } = useText<TFilters>({ name, filters, setFilters });

  return (
    <TextField
      fullWidth
      dataTestId={label}
      label={t(label)}
      value={value}
      onChange={change}
    />
  );
};

export default Text;
