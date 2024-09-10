import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { SingleAutocompleteField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { labelSelectTimeFormat } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import locales from './locales.json';
import { useLocale } from './useLocale';

const Locale = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { value, changeValue } = useLocale({ propertyName });

  const { locale } = useAtomValue(userAtom);
  const formattedUserLocale = locale.replace('_', '-');

  const { canEditField } = useCanEditProperties();

  return (
    <SingleAutocompleteField
      disabled={!canEditField}
      label={t(labelSelectTimeFormat)}
      options={locales}
      value={value ?? locales.find(({ id }) => equals(id, formattedUserLocale))}
      onChange={changeValue}
    />
  );
};

export default Locale;
