import { ChangeEvent, useState } from 'react';

import { T, always, clamp, cond, isEmpty } from 'ramda';

import TextField, { TextProps } from '../Text';

export interface NumberProps
  extends Omit<TextProps, 'defaultValue' | 'onChange'> {
  /**
   * The initial value which will be used by the input for the first render
   */
  defaultValue?: number;
  /**
   *  This value will be used when the input is cleared
   */
  fallbackValue?: number;
  /**
   * The change function with the actual value as parameter. This parameter will be the value when the input is filled but it will be the fallbackValue when the input is cleared out
   */
  onChange: (actualValue: number) => void;
}

const NumberField = ({
  fallbackValue = 0,
  defaultValue,
  onChange,
  ...props
}: NumberProps): JSX.Element => {
  const [placeholder, setPlaceholder] = useState<string | undefined>();
  const [actualValue, setActualValue] = useState(
    defaultValue ? `${defaultValue}` : ''
  );

  const { textFieldSlotsAndSlotProps } = props;

  const changeValue = (event: ChangeEvent<HTMLInputElement>): void => {
    const inputValue = event.target.value;

    const number = Number(inputValue);
    const campledNumber = cond([
      [() => isEmpty(inputValue), always(fallbackValue)],
      [() => Number.isNaN(number), always(number)],
      [
        T,
        always(
          clamp(
            textFieldSlotsAndSlotProps?.slotProps?.htmlInput?.min ||
              Number.NEGATIVE_INFINITY,
            textFieldSlotsAndSlotProps?.slotProps?.htmlInput?.max ||
              Number.POSITIVE_INFINITY,
            number
          )
        )
      ]
    ])();

    onChange(campledNumber);
    setPlaceholder(isEmpty(inputValue) ? `${fallbackValue}` : undefined);
    setActualValue(isEmpty(inputValue) ? inputValue : `${campledNumber}`);
  };

  return (
    <TextField
      type="number"
      value={actualValue}
      onChange={changeValue}
      {...props}
      textFieldSlotsAndSlotProps={{
        slotProps: {
          htmlInput: { ...textFieldSlotsAndSlotProps?.slotProps?.htmlInput }
        }
      }}
      placeholder={
        placeholder || (!defaultValue ? `${fallbackValue}` : undefined)
      }
    />
  );
};

export default NumberField;
