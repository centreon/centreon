import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Stack, Typography } from '@mui/material';

import { SelectField } from '@centreon/ui';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { WidgetPropertyProps } from '../../../models';

import Tooltip from '../common/Tooltip';
import { useSelectStyles } from './Select.styles';
import useSelect from './useSelect';

const Select = ({
  propertyName,
  label,
  options,
  isInGroup,
  secondaryLabel
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
      <Stack alignItems="center" direction="row" gap={1.5}>
        <Label>{t(label)}</Label>
        <Tooltip secondaryLabel={secondaryLabel} propertyName={propertyName} />
      </Stack>
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
