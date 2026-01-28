import { useState } from 'react';

import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { Provider, createStore } from 'jotai';

import { ListingVariant, userAtom } from '@centreon/ui-context';

import DateInput from './DateInput';

dayjs.extend(utc);
dayjs.extend(timezone);

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

const initialize = (args): void => {
  // Create a store with consistent locale settings
  const store = createStore();
  store.set(userAtom, {
    ...retrievedUser,
    locale: 'fr_FR.UTF8', // Force French locale for consistent DD/MM/YYYY format
    timezone: 'Europe/Paris'
  });

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestWrapper {...args} />
      </Provider>
    )
  });
};

describe('DateInput Component', () => {
  beforeEach(() => {
    cy.clock(new Date('2025-08-21T10:00:00.000Z'));
  });

  afterEach(() => {
    cy.clock().then((clock) => clock.restore());
  });

  it('should render with default date when no initial date is provided', () => {
    initialize({});

    cy.get('[data-testid="test-date-calendarContainer"]').should('be.visible');
    cy.contains('Until').should('be.visible');
    cy.get('[data-testid="calendarInput"]').should('be.visible');
  });

  it('should render with provided initial date', () => {
    const initialDate = new Date('2025-08-25T14:30:00+02:00'); // Explicit timezone to match Europe/Paris
    initialize({ initialDate });

    cy.get('[data-testid="calendarInput"]').should(
      'have.value',
      '25/08/2025 14:30'
    );
    cy.get('[data-testid="current-date"]').should(
      'contain',
      '2025-08-25 14:30'
    );
  });

  it('should show error for invalid date format', () => {
    initialize({});

    cy.get('[data-testid="calendarInput"]').clear().type('invalid-date').blur();

    cy.contains('invalid date').should('be.visible');
  });

  it('should clear error when valid date is entered', () => {
    initialize({});

    // First enter invalid date
    cy.get('[data-testid="calendarInput"]').clear().type('invalid-date').blur();

    cy.contains('invalid date').should('be.visible');

    // Then enter valid date
    cy.get('[data-testid="calendarInput"]')
      .clear()
      .type('25/12/2025 15:45')
      .blur();

    cy.contains('invalid date').should('not.exist');
  });

  it('should not commit invalid date when pressing Enter', () => {
    const initialDate = new Date('2025-08-25T14:30:00+02:00'); // Explicit timezone
    initialize({ initialDate });

    cy.get('[data-testid="calendarInput"]')
      .clear()
      .type('invalid-date')
      .type('{enter}');

    // Should show error and not update the date
    cy.contains('invalid date').should('be.visible');
    cy.get('[data-testid="current-date"]').should(
      'contain',
      '2025-08-25 14:30'
    );
    cy.get('[data-testid="calendar-display"]').should(
      'contain',
      'Calendar shown'
    );
  });

  it('should use custom label for test id', () => {
    initialize({ label: 'custom-date-picker' });

    cy.get('[data-testid="custom-date-picker-calendarContainer"]').should(
      'be.visible'
    );
  });

  it('should handle date changes through the date picker interface', () => {
    initialize({});

    // Click on the calendar input to open date picker
    cy.get('[data-testid="calendarInput"]').click();

    // The DateTimePickerInput component should be interactive
    // Note: Specific date picker interactions would depend on the @centreon/ui implementation
    cy.get('[data-testid="calendarInput"]').should('be.focused');
  });

  it('should maintain date format consistency', () => {
    const testDate = new Date('2025-12-25T15:45:30+01:00'); // Explicit timezone for winter time
    initialize({ initialDate: testDate });

    // Check that the date is displayed in the expected format
    cy.get('[data-testid="calendarInput"]').should(
      'have.value',
      '25/12/2025 15:45'
    );
  });

  it('should handle edge case with null initial date', () => {
    initialize({ initialDate: null });

    cy.get('[data-testid="current-date"]').should('contain', 'No date set');

    // Should still render calendar input with default date
    cy.get('[data-testid="calendarInput"]').should('be.visible');
  });

  it('should handle dayjs validation correctly', () => {
    initialize({});

    // Test with clearly invalid date format
    cy.get('[data-testid="calendarInput"]').clear().type('not-a-date').blur();

    cy.contains('invalid date').should('be.visible');

    // Clear the error by entering a valid date
    cy.get('[data-testid="calendarInput"]')
      .clear()
      .type('21/08/2025 10:00')
      .blur();

    cy.contains('invalid date').should('not.exist');
  });
});
