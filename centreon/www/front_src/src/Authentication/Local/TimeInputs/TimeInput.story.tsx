import { useState } from 'react';

import { labelMinute, labelMinutes } from '../translatedLabels';
import TimeInput, { type TimeInputProps } from './TimeInput';

type Props = Partial<Omit<TimeInputProps, 'onChange'>>;

// Minutes input as configured by the password policy form; specs override the
// serializable props. The last value passed to onChange is recorded.
export const Minutes = (props: Props): JSX.Element => {
  const [lastChange, setLastChange] = useState('');

  return (
    <>
      <TimeInput
        inputLabel="input"
        labels={{ plural: labelMinutes, singular: labelMinute }}
        name="input"
        timeValue={0}
        unit="minutes"
        {...props}
        onChange={(value: number) => setLastChange(String(value))}
      />
      <form hidden>
        <input data-testid="last-change" readOnly value={lastChange} />
      </form>
    </>
  );
};
