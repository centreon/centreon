import { ListingVariant, userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';

import DateTimePickerInput from './DateTimePickerInput';
import { CustomTimePeriodProperty } from './models';

interface Props {
  date: string;
  timezone: string;
}

export const Default = ({ date, timezone }: Props): JSX.Element => {
  const store = createStore();
  store.set(userAtom, {
    alias: 'Admin alias',
    default_page: '/monitoring/resources',
    isExportButtonEnabled: true,
    locale: 'en_US',
    name: 'Admin',
    timezone,
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  } as never);

  return (
    <Provider store={store}>
      <DateTimePickerInput
        changeDate={() => undefined}
        date={new Date(date)}
        property={CustomTimePeriodProperty.start}
      />
    </Provider>
  );
};
