import { useState } from 'react';

import NumberField from './Number';

interface Props {
  autoSize?: boolean;
  defaultValue?: number;
  fallbackValue?: number;
  min?: number;
}

// Records the last value the field reported in `number-value`.
export const Default = ({ min, ...props }: Props): JSX.Element => {
  const [value, setValue] = useState<number | undefined>();

  return (
    <>
      <NumberField
        {...props}
        dataTestId="test"
        onChange={setValue}
        textFieldSlotsAndSlotProps={
          min === undefined ? undefined : { slotProps: { htmlInput: { min } } }
        }
      />
      <output data-testid="number-value">{String(value)}</output>
    </>
  );
};
