import { TextField } from '@centreon/ui';
import { useTranslation } from 'react-i18next';
import useText from './useText';

const Text = ({ label, name, filters, setFilters }) => {
  const { t } = useTranslation();

  const { change, value } = useText({ name, filters, setFilters });

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
