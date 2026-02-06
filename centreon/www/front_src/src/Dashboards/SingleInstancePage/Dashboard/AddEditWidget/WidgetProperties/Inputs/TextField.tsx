import { Typography } from '@mui/material';

import { TextField, usePluralizedTranslation } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { clamp, equals } from 'ramda';
import { type ChangeEvent, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import Subtitle from '../../../components/Subtitle';
import { useCanEditProperties } from '../../../hooks/useCanEditDashboard';
import type { Widget, WidgetPropertyProps } from '../../models';
import { useTextFieldStyles } from './Inputs.styles';
import { getProperty } from './utils';

const WidgetTextField = ({
  propertyName,
  label,
  text,
  required = false,
  disabled = false,
  className,
  isInGroup,
  secondaryLabel,
  ignoreError
}: WidgetPropertyProps & { ignoreError?: boolean }): JSX.Element => {
  const { t } = useTranslation();
  const { pluralizedT } = usePluralizedTranslation();

  const { classes } = useTextFieldStyles({ hasMarginBottom: !!secondaryLabel });

  const { errors, values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const { canEditField } = useCanEditProperties();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const error = useMemo<string | undefined>(
    () => getProperty({ obj: errors, propertyName }),
    [getProperty({ obj: errors, propertyName })]
  );

  const isTouched = useMemo<string | undefined>(
    () => getProperty({ obj: touched, propertyName }),
    [getProperty({ obj: touched, propertyName })]
  );

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched(`options.${propertyName}`, true);

    const newText = event.target.value;
    setFieldValue(
      `options.${propertyName}`,
      equals(text?.type, 'number') &&
        equals(event.nativeEvent.inputType, 'insertReplacementText')
        ? Number(newText)
        : newText
    );
  };

  const blur = useCallback(
    (event) => {
      if (!equals(text?.type, 'number')) {
        return;
      }
      setFieldValue(
        `options.${propertyName}`,
        equals(event.target.value, '')
          ? ''
          : clamp(text?.min, text?.max, Number(event.target.value))
      );
    },
    [text]
  );

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div className={classes.container}>
      {secondaryLabel && <Label>{t(secondaryLabel)}</Label>}
      <div className={classes.inputContainer}>
        <TextField
          autoSize={text?.autoSize}
          autoSizeDefaultWidth={30}
          className={className}
          dataTestId={label}
          disabled={!canEditField || disabled}
          error={ignoreError ? undefined : isTouched && error}
          fullWidth
          helperText={ignoreError ? undefined : isTouched && error}
          label={t(label) || ''}
          multiline={text?.multiline || false}
          onBlur={blur}
          onChange={change}
          required={required}
          size={text?.size || 'small'}
          textFieldSlotsAndSlotProps={{
            slotProps: {
              htmlInput: {
                'aria-label': t(label) as string,
                max: text?.max,
                min: text?.min,
                step: text?.step || '1'
              }
            }
          }}
          type={text?.type || 'text'}
          value={value ?? ''}
        />
        {text?.unit && (
          <Typography>
            {pluralizedT({
              count:
                equals(text.type, 'number') && text?.pluralize
                  ? Number(value)
                  : 1,
              label: text.unit
            })}
          </Typography>
        )}
      </div>
    </div>
  );
};

export default WidgetTextField;
