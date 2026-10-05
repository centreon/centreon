import { Button, ButtonGroup, FormGroup, FormLabel } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { equals, path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '../..';
import type { InputPropsWithoutGroup } from './models';

// One choice among a few, as joined buttons. Unlike the radio, each option's
// value is stored exactly as given.
const SegmentedButtons = ({
  dataTestId,
  fieldName,
  label,
  segmentedButtons,
  getDisabled,
  additionalMemoProps
}: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<FormikValues>();

  const value = path(split('.', fieldName), values);

  const disabled = getDisabled?.(values) || false;

  const select = (optionValue: string) => (): void => {
    setFieldTouched(fieldName, true, false);
    setFieldValue(fieldName, optionValue);
  };

  return useMemoComponent({
    Component: (
      <FormGroup>
        <FormLabel>{t(label)}</FormLabel>
        <ButtonGroup
          aria-label={t(label)}
          // As wide as its column, up to what three labels need.
          className="max-w-[412px]"
          color="primary"
          data-testid={dataTestId}
          disabled={disabled}
          fullWidth
          size="small"
        >
          {segmentedButtons?.options.map(
            ({ label: optionLabel, value: optionValue }) => {
              const isSelected = equals(value, optionValue);

              return (
                <Button
                  aria-pressed={isSelected}
                  data-testid={`${dataTestId}-${optionValue}`}
                  key={optionValue}
                  onClick={select(optionValue)}
                  // The button's own padding and type would make it 36px.
                  sx={{
                    fontSize: 14,
                    fontWeight: 500,
                    height: 28,
                    lineHeight: '21px',
                    px: 1.5,
                    py: 0,
                    textTransform: 'none'
                  }}
                  variant={isSelected ? 'contained' : 'outlined'}
                >
                  {t(optionLabel)}
                </Button>
              );
            }
          )}
        </ButtonGroup>
      </FormGroup>
    ),
    memoProps: [value, disabled, segmentedButtons, additionalMemoProps]
  });
};

export default SegmentedButtons;
