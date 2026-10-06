import { Button, ButtonGroup, FormGroup, FormLabel } from '@mui/material';

import { type FormikValues, useFormikContext } from 'formik';
import { equals, path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '../..';
import type { InputPropsWithoutGroup } from './models';

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
                  className="h-7 px-3 py-0 font-medium text-sm leading-[21px] normal-case"
                  data-testid={`${dataTestId}-${optionValue}`}
                  key={optionValue}
                  onClick={select(optionValue)}
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
