import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { SelectField } from '@centreon/ui';

import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useSelect from './useSelect';
import { useSelectStyles } from './Select.styles';

const Select = ({
  propertyName,
  label,
  options
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useSelectStyles();
  const { t } = useTranslation();

  const { value, setSelect } = useSelect(propertyName);

  const { canEditField } = useCanEditProperties();

  const translatedOptions = (options || []).map(({ id, name }) => ({
    id,
    name: t(name)
  }));

  return (
    <div className={classes.container}>
      <Typography>
        <strong>{t(label)}</strong>
      </Typography>
      <SelectField
        dataTestId={label}
        disabled={!canEditField}
        options={translatedOptions}
        selectedOptionId={value || ''}
        onChange={setSelect}
      />
    </div>
  );
};

export default Select;
