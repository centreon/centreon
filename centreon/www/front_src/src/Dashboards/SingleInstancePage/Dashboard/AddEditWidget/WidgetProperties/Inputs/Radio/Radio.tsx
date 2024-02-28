import { ChangeEvent, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { RadioGroup, FormControlLabel, Radio } from '@mui/material';

import { Widget, WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import Subtitle from '../../../../components/Subtitle';
import { getProperty } from '../utils';

const WidgetRadio = ({
  propertyName,
  options,
  label
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

  return (
    <div>
      <Subtitle>{t(label)}</Subtitle>
      <RadioGroup value={value} onChange={change}>
        {(options || []).map(({ id, name }) => (
          <FormControlLabel
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
