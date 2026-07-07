import { ListingVariant, userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { createStore, Provider } from 'jotai';
import { useState } from 'react';

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
}: TestWrapperProps) => {
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

const initialize = (args) => {
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

// The MUI X v9 picker renders an accessible field made of editable sections.
// Typing happens section by section: focusing the field selects the first
// section and digits auto-advance through day, month, year, hours, minutes.
// The formatted value is exposed on the hidden `calendarInput` element.
const typeDate = (digits: string): void => {
  cy.get('[data-testid="calendarField"] [role="spinbutton"]').first().click();
  cy.focused().type(digits);
};

// Emptying a section makes the date incomplete; the next keystroke triggers
// the component validation and surfaces the error.
const emptyOneSection = (): void => {
  cy.get('[data-testid="calendarField"]').click();
  cy.focused().type('{selectall}{backspace}{rightArrow}');
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
    cy.get('[data-testid="calendarField"]').should('be.visible');
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

  it('should update date when typing in the input field', () => {
    initialize({});

    typeDate('251220251545');

    // The date should be updated in the input but not yet committed
    cy.get('[data-testid="calendarInput"]').should(
      'have.value',
      '25/12/2025 15:45'
    );
  });

  it('should commit date when pressing Enter key', () => {
    initialize({});

    typeDate('251220251545{enter}');

    cy.get('[data-testid="current-date"]').should(
      'contain',
      '2025-12-25 15:45'
    );
    cy.get('[data-testid="calendar-display"]').should(
      'contain',
      'Calendar hidden'
    );
  });

  it('should show error for invalid date format', () => {
    initialize({});

    emptyOneSection();

    cy.contains('invalid date').should('be.visible');
  });

  it('should clear error when valid date is entered', () => {
    initialize({});

    // First make the date invalid
    emptyOneSection();

    cy.contains('invalid date').should('be.visible');

    // Then enter a valid date (trailing key re-runs validation on the now-valid date)
    typeDate('251220251545{rightArrow}');

    cy.contains('invalid date').should('not.exist');
  });

  it('should not commit invalid date when pressing Enter', () => {
    const initialDate = new Date('2025-08-25T14:30:00+02:00'); // Explicit timezone
    initialize({ initialDate });

    cy.get('[data-testid="calendarField"]').click();
    cy.focused().type('{selectall}{backspace}{enter}');

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

    // Clicking the field focuses an editable section of the picker
    cy.get('[data-testid="calendarField"]').click();

    cy.focused().should('have.attr', 'role', 'spinbutton');
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

    // Should still render the calendar field with the default date
    cy.get('[data-testid="calendarField"]').should('be.visible');
  });

  it('should handle dayjs validation correctly', () => {
    initialize({});

    // Make the date invalid by emptying a section
    emptyOneSection();

    cy.contains('invalid date').should('be.visible');

    // Clear the error by entering a valid date (trailing key re-runs validation)
    typeDate('210820251000{rightArrow}');

    cy.contains('invalid date').should('not.exist');
  });

  it('should properly handle time components in date', () => {
    initialize({});

    typeDate('210820252330{enter}');

    cy.get('[data-testid="current-date"]').should(
      'contain',
      '2025-08-21 23:30'
    );
    cy.get('[data-testid="calendar-display"]').should(
      'contain',
      'Calendar hidden'
    );
  });
});
