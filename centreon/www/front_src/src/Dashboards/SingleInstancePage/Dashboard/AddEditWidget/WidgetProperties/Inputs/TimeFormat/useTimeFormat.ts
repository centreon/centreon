import { useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';
import { isNotNil } from 'ramda';

import { userAtom } from '@centreon/ui-context';

import { Widget, WidgetPropertyProps } from '../../../models';
import { localeInputKeyDerivedAtom } from '../../../atoms';
import { getProperty } from '../utils';

export const useTimeFormat = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): void => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const localeInputKey = useAtomValue(localeInputKeyDerivedAtom);
  const { locale } = useAtomValue(userAtom);

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  ) as string;

  const localeValue =
    localeInputKey && (values.options[localeInputKey] as string | undefined);

  useEffect(() => {
    if (isNotNil(value)) {
      return;
    }
    const isMeridiem =
      dayjs()
        .locale(localeValue ?? locale.replace('_', '-'))
        .format('LT').length > 5;
    setFieldValue(`options.${propertyName}`, isMeridiem ? '12' : '24');
  }, [localeValue]);
};
