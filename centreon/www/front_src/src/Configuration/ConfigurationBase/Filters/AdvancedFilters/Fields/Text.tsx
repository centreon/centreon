import { TextField } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

const Text = ({ label, name, filters, change }) => {
  const { t } = useTranslation();

  return (
    <TextField
      fullWidth
      dataTestId={label}
      label={t(label)}
      value={filters[name]}
      onChange={change(name)}
    />
  );
};

export default Text;
