import { useEffect } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';

import { userAtom } from '@centreon/ui-context';
import { SelectEntry } from '@centreon/ui';

import { Widget, WidgetPropertyProps } from '../../../models';
import { localeInputKeyDerivedAtom } from '../../../atoms';

export const useTimeFormat = ({
  propertyName
}: Pick<WidgetPropertyProps, 'propertyName'>): void => {
  const { values, setFieldValue } = useFormikContext<Widget>();

  const localeInputKey = useAtomValue(localeInputKeyDerivedAtom);
  const { locale } = useAtomValue(userAtom);

  const localeValue =
    localeInputKey &&
    (values.options[localeInputKey] as SelectEntry | undefined);

  useEffect(() => {
    const isMeridiem =
      dayjs()
        .locale(localeValue?.id ?? locale.replace('_', '-'))
        .format('LT').length > 5;
    setFieldValue(`options.${propertyName}`, isMeridiem ? '12' : '24');
  }, [localeValue]);
};
