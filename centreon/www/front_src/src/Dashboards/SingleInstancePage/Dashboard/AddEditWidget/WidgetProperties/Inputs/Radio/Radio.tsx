import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Radio, RadioGroup, Typography } from '@mui/material';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

const WidgetRadio = ({
  propertyName,
  options,
  label,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const { canEditField } = useCanEditProperties();

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched(`options.${propertyName}`, true);
    setFieldValue(`options.${propertyName}`, event.target.value);
  };

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <Label>{t(label)}</Label>
      <RadioGroup value={value} onChange={change}>
        {(options || []).map(({ id, name }) => (
          <FormControlLabel
            aria-label={t(name)}
            checked={equals(id, value)}
            control={<Radio />}
            disabled={!canEditField}
            key={id}
            label={t(name)}
            value={id}
          />
        ))}
      </RadioGroup>
    </div>
  );
};

export default WidgetRadio;
