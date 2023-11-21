import { useMemo } from 'react';

import { useFormikContext } from 'formik';

import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseSingleMetricGraphTypeState {
  changeType: (type: string) => () => void;
  value: string;
}

const useSingleMetricGraphType = ({
  propertyName
}: WidgetPropertyProps): UseSingleMetricGraphTypeState => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as string;

  const changeType = (type: string) => (): void => {
    setFieldValue(`options.${propertyName}`, type);
  };

  return {
    changeType,
    value
  };
};

export default useSingleMetricGraphType;
