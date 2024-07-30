import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { SingleAutocompleteField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { WidgetPropertyProps } from '../../../models';
import { labelSelectTimezone } from '../../../../translatedLabels';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import { useTimezone } from './useTimezone';
import timezones from './timezones.json';

const Timezone = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { value, changeValue } = useTimezone({ propertyName });

  const { timezone } = useAtomValue(userAtom);

  const { canEditField } = useCanEditProperties();

  return (
    <SingleAutocompleteField
      disabled={!canEditField}
      label={t(labelSelectTimezone)}
      options={timezones}
      value={value ?? { id: timezone, name: timezone }}
      onChange={changeValue}
    />
  );
};

export default Timezone;
