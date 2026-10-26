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
        <FormLabel className="font-bold">{t(label)}</FormLabel>
        {/* In a column narrower than the segments, the group scrolls inside it
            rather than overflowing into what is next to it. */}
        <div className="max-w-full self-start overflow-x-auto">
          <ButtonGroup
            aria-label={t(label)}
            // Equal segments, each as wide as the longest label (26 spacing
            // units, 104px, at least), whatever the width of the column.
            className="inline-grid auto-cols-[minmax(calc(var(--spacing)*26),1fr)] grid-flow-col"
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
        </div>
      </FormGroup>
    ),
    memoProps: [value, disabled, segmentedButtons, additionalMemoProps]
  });
};

export default SegmentedButtons;
