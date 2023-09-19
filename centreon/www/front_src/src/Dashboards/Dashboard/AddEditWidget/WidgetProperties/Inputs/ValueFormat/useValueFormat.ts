import { ChangeEvent, useMemo } from 'react';

import { useFormikContext } from 'formik';

import { Widget } from '../../../models';
import { getProperty } from '../utils';

interface UseValueFormatState {
  changeType: (event: ChangeEvent<HTMLInputElement>) => void;
  value: 'raw' | 'human';
}

const useValueFormat = (propertyName: string): UseValueFormatState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<'raw' | 'human' | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as 'raw' | 'human';

  const changeType = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(`options.${propertyName}`, event.target.value);
  };

  return {
    changeType,
    value
  };
};

export default useValueFormat;
