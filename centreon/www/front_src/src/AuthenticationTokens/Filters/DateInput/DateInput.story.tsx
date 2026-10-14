import { ListingVariant, userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import { createStore, Provider } from 'jotai';
import { useMemo, useState } from 'react';

import DateInput from './DateInput';

interface Props {
  /** ISO date; Date objects do not cross the Node/browser boundary. */
  initialDate?: string | null;
  label?: string;
}

// Owns the date and calendar state the input drives, and displays them.
const DateInputWithState = ({
  initialDate = null,
  label = 'test-date'
}: Props): JSX.Element => {
  const [date, setDate] = useState<Date | null>(
    initialDate ? new Date(initialDate) : null
  );
  const [displayCalendar, setDisplayCalendar] = useState(true);

  return (
    <div>
      <DateInput
        dataDate={{ date, setDate } as never}
        label={label}
        setDisplayCalendar={setDisplayCalendar as never}
      />
      <div data-testid="current-date">
        {date
          ? dayjs(date).tz('Europe/Paris').format('YYYY-MM-DD HH:mm')
          : 'No date set'}
      </div>
      <div data-testid="calendar-display">
        {displayCalendar ? 'Calendar shown' : 'Calendar hidden'}
      </div>
    </div>
  );
};

export const Default = (props: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    // French locale for a stable DD/MM/YYYY format.
    created.set(userAtom, {
      alias: 'Test User',
      canManageApiTokens: true,
      default_page: '/monitoring/resources',
      isExportButtonEnabled: true,
      locale: 'fr_FR.UTF8',
      name: 'Test User',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    } as never);

    return created;
  }, []);

  return (
    <Provider store={store}>
      <DateInputWithState {...props} />
    </Provider>
  );
};
