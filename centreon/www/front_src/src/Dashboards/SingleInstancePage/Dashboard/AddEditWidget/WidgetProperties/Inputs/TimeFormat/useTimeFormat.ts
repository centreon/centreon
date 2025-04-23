import { useEffect } from 'react';

import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';

import { SelectEntry } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { localeInputKeyDerivedAtom } from '../../../atoms';
import { Widget, WidgetPropertyProps } from '../../../models';

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
