import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { pick } from 'ramda';

import { SelectEntry } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../../models';
import { getProperty } from '../utils';

interface UseLocaleState {
  changeValue: (_, option: SelectEntry) => void;
  value?: SelectEntry;
}

export const useLocale = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): UseLocaleState => {
  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<SelectEntry | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as SelectEntry;

  const changeValue = (_, option: SelectEntry): void => {
    const selectedOption = option ? pick(['id', 'name'], option) : {};

    setFieldValue(`options.${propertyName}`, selectedOption);
    setFieldTouched(`options.${propertyName}`, true, false);
  };

  return {
    changeValue,
    value
  };
};
