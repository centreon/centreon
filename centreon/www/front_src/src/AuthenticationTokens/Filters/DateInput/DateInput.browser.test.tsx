// The picker formats dates with the user's locale, which the page loads.
import 'dayjs/locale/fr';

import { ListingVariant, type User, userAtom } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import dayjs from 'dayjs';
import { createStore, Provider } from 'jotai';
import { useState } from 'react';

import DateInput from './DateInput';

const retrievedUser = {
  alias: 'Test User',
  canManageApiTokens: true,
  default_page: '/monitoring/resources',
  is_export_button_enabled: true,
  isExportButtonEnabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Test User',
  use_deprecated_pages: false,
  user_interface_density: ListingVariant.compact
};

interface TestWrapperProps {
  initialDate?: Date | null;
  label?: string;
}

const TestWrapper = ({
  initialDate = null,
  label = 'test-date'
}: TestWrapperProps): JSX.Element => {
  const [date, setDate] = useState<Date | null>(initialDate);
  const [displayCalendar, setDisplayCalendar] = useState(true);

  return (
    <div>
      <DateInput
        dataDate={{ date, setDate }}
        label={label}
        setDisplayCalendar={setDisplayCalendar}
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

const initialize = (props: TestWrapperProps): void => {
  const store = createStore();

  // The French locale gives the DD/MM/YYYY format; dates are shown in the
  // user's time zone, whatever the machine's.
  store.set(userAtom, {
    ...retrievedUser,
    locale: 'fr_FR.UTF8',
    timezone: 'Europe/Paris'
  } as unknown as User);

  render(
    <Provider store={store}>
      <TestWrapper {...props} />
    </Provider>
  );
};

const getInput = (): HTMLInputElement =>
  screen.getByTestId('calendarInput') as HTMLInputElement;

// The picker field is split in sections (day, month, ...) and does not take
// the characters of `userEvent.type`: the keys are typed on the field the
// clear left focused instead.
const typeDate = async (text: string): Promise<void> => {
  await userEvent.clear(getInput());
  await userEvent.keyboard(text);
};

const blur = (): void => {
  act(() => {
    getInput().blur();
  });
};

describe('DateInput Component', () => {
  beforeEach(() => {
    expect.hasAssertions();
    // Only the date is fixed: the timers keep running for the page.
    rs.useFakeTimers({ toFake: ['Date'] });
    rs.setSystemTime(new Date('2025-08-21T10:00:00.000Z'));
  });

  afterEach(() => {
    rs.useRealTimers();
  });

  it('should render with default date when no initial date is provided', async () => {
    initialize({});

    expect(
      await screen.findByTestId('test-date-calendarContainer')
    ).toBeVisible();
    expect(screen.getByText('Until')).toBeVisible();
    expect(getInput()).toBeVisible();
    // The fixed clock's date (10:00 UTC), in the user's time zone.
    await waitFor(() => expect(getInput()).toHaveValue('21/08/2025 12:00'));
  });

  it('should render with provided initial date', async () => {
    const initialDate = new Date('2025-08-25T14:30:00+02:00');
    initialize({ initialDate });

    await waitFor(() => expect(getInput()).toHaveValue('25/08/2025 14:30'));
    expect(screen.getByTestId('current-date')).toHaveTextContent(
      '2025-08-25 14:30'
    );
  });

  it('should update date when typing in the input field', async () => {
    initialize({});

    await typeDate('25/12/2025 15:45');

    // The input shows the typed date; nothing is committed yet.
    await waitFor(() => expect(getInput()).toHaveValue('25/12/2025 15:45'));
  });

  it('should commit date when pressing Enter key', async () => {
    initialize({});

    await typeDate('25/12/2025 15:45');
    await userEvent.type(getInput(), '{Enter}');

    await waitFor(() =>
      expect(screen.getByTestId('current-date')).toHaveTextContent(
        '2025-12-25 15:45'
      )
    );
    expect(screen.getByTestId('calendar-display')).toHaveTextContent(
      'Calendar hidden'
    );
  });

  it('should show error for invalid date format', async () => {
    initialize({});

    await typeDate('invalid-date');
    blur();

    expect(await screen.findByText('invalid date')).toBeVisible();
  });

  it('should clear error when valid date is entered', async () => {
    initialize({});

    await typeDate('invalid-date');
    blur();

    expect(await screen.findByText('invalid date')).toBeVisible();

    await typeDate('25/12/2025 15:45');
    blur();

    await waitFor(() =>
      expect(screen.queryByText('invalid date')).not.toBeInTheDocument()
    );
  });

  it('should not commit invalid date when pressing Enter', async () => {
    const initialDate = new Date('2025-08-25T14:30:00+02:00');
    initialize({ initialDate });

    await typeDate('invalid-date');
    await userEvent.type(getInput(), '{Enter}');

    expect(await screen.findByText('invalid date')).toBeVisible();
    expect(screen.getByTestId('current-date')).toHaveTextContent(
      '2025-08-25 14:30'
    );
    expect(screen.getByTestId('calendar-display')).toHaveTextContent(
      'Calendar shown'
    );
  });

  it('should use custom label for test id', async () => {
    initialize({ label: 'custom-date-picker' });

    expect(
      await screen.findByTestId('custom-date-picker-calendarContainer')
    ).toBeVisible();
  });

  it('should handle date changes through the date picker interface', async () => {
    initialize({});

    await userEvent.click(getInput());

    expect(getInput()).toHaveFocus();
  });

  it('should maintain date format consistency', async () => {
    const testDate = new Date('2025-12-25T15:45:30+01:00');
    initialize({ initialDate: testDate });

    await waitFor(() => expect(getInput()).toHaveValue('25/12/2025 15:45'));
  });

  it('should handle edge case with null initial date', async () => {
    initialize({ initialDate: null });

    expect(screen.getByTestId('current-date')).toHaveTextContent('No date set');

    // The input still shows a date: the current one, in the user's time zone.
    expect(await screen.findByTestId('calendarInput')).toBeVisible();
    await waitFor(() => expect(getInput()).toHaveValue('21/08/2025 12:00'));
  });

  it('should handle dayjs validation correctly', async () => {
    initialize({});

    await typeDate('not-a-date');
    blur();

    expect(await screen.findByText('invalid date')).toBeVisible();

    await typeDate('21/08/2025 10:00');
    blur();

    await waitFor(() =>
      expect(screen.queryByText('invalid date')).not.toBeInTheDocument()
    );
  });

  it('should properly handle time components in date', async () => {
    initialize({});

    await typeDate('21/08/2025 23:30');
    await userEvent.type(getInput(), '{Enter}');

    await waitFor(() =>
      expect(screen.getByTestId('current-date')).toHaveTextContent(
        '2025-08-21 23:30'
      )
    );
    expect(screen.getByTestId('calendar-display')).toHaveTextContent(
      'Calendar hidden'
    );
  });
});
