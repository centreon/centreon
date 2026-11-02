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
        {!segmentedButtons?.hideLabel && (
          <FormLabel className="font-bold">{t(label)}</FormLabel>
        )}
        {/* In a column narrower than the three minimum widths, the group scrolls
            inside it rather than overflowing into what is next to it. */}
        <div className="max-w-full self-start overflow-x-auto">
          <ButtonGroup
            aria-label={t(label)}
            className="max-w-full"
            color="primary"
            data-testid={dataTestId}
            disabled={disabled}
            size="small"
          >
            {segmentedButtons?.options.map(
              ({ label: optionLabel, value: optionValue }) => {
                const isSelected = equals(value, optionValue);

                // As in the design: every segment is 26 spacing units (104px)
                // wide at least, and grows with its own label. A label wider
                // than the room left is cut with an ellipsis, its full text
                // in the tooltip.
                return (
                  <Button
                    aria-pressed={isSelected}
                    className="h-7 min-w-26 px-3 py-0 font-medium text-sm leading-[21px] normal-case"
                    data-testid={`${dataTestId}-${optionValue}`}
                    key={optionValue}
                    onClick={select(optionValue)}
                    title={t(optionLabel)}
                    variant={isSelected ? 'contained' : 'outlined'}
                  >
                    <span className="min-w-0 truncate">{t(optionLabel)}</span>
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
