import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { SelectField } from '@centreon/ui';

import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import Subtitle from '../../../../components/Subtitle';

import useSelect from './useSelect';
import { useSelectStyles } from './Select.styles';

const Select = ({
  propertyName,
  label,
  options,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useSelectStyles();
  const { t } = useTranslation();

  const { value, setSelect } = useSelect(propertyName);

  const { canEditField } = useCanEditProperties();

  const translatedOptions = (options || []).map(({ id, name }) => ({
    id,
    name: t(name)
  }));

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div className={classes.container}>
      <Label>{t(label)}</Label>
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
