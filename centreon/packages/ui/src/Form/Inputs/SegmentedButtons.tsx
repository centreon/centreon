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
          // Equal segments, each as wide as the longest label (103px at least,
          // the design's size), whatever the width of the column.
          className="inline-grid max-w-full auto-cols-[minmax(103px,1fr)] grid-flow-col self-start"
          color="primary"
          data-testid={dataTestId}
          disabled={disabled}
          size="small"
        >
          {segmentedButtons?.options.map(
            ({ label: optionLabel, value: optionValue }) => {
              const isSelected = equals(value, optionValue);

              return (
                <Button
                  aria-pressed={isSelected}
                  className="h-7 whitespace-nowrap px-3 py-0 font-medium text-sm leading-[21px] normal-case"
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
