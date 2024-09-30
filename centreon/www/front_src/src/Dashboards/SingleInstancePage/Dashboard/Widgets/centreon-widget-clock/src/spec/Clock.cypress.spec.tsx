import { createStore, Provider } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import Widget from '..';
import { PanelOptions } from '../models';

import 'dayjs/locale/de';

const countdown = 1722587072000;
const farCountdown = 1723830148000;
const invalidCountdown = 2415621;

const normalDisplay = {
  forceHeight: 400,
  forceWidth: 600
};
const smallDisplay = {
  forceHeight: 300,
  forceWidth: 199
};

const initialize = ({
  hasDescription = false,
  isSmall,
  ...options
}: PanelOptions & { hasDescription?: boolean; isSmall?: boolean }): void => {
  const store = createStore();

  store.set(userAtom, { locale: 'de_DE', timezone: 'Europe/Helsinki' });
  cy.clock(1722584072000);

  cy.mount({
    Component: (
      <Provider store={store}>
        <div style={{ height: '400px', position: 'relative', width: '600px' }}>
          <Widget
            {...(isSmall ? smallDisplay : normalDisplay)}
            dashboardId={1}
            globalRefreshInterval={{
              interval: null,
              type: 'manual'
            }}
            hasDescription={hasDescription}
            id="dashboard"
            panelOptions={options}
            refreshCount={0}
            widgetPrefixQuery="prefix"
          />
        </div>
      </Provider>
    )
  });
};

describe('Clock-Timer', () => {
  describe('Clock', () => {
    it('displays the widget with default properties', () => {
      initialize({
        displayType: 'clock',
        showDate: true,
        showTimezone: true,
        timeFormat: '12'
      });

      cy.contains('Europe/Helsinki').should('be.visible');
      cy.findByTestId('QueryBuilderIcon').should('be.visible');
      cy.contains('02.08.2024').should('be.visible');
      cy.contains('10:34').should('be.visible');
      cy.contains('AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the widget with small width', () => {
      initialize({
        displayType: 'clock',
        isSmall: true,
        showDate: true,
        showTimezone: true,
        timeFormat: '12'
      });

      cy.contains('Europe/Helsinki').should('not.exist');
      cy.findByTestId('PublicIcon').should('be.visible');
      cy.findByLabelText('Europe/Helsinki').should('be.visible');
      cy.contains('02.08.2024').should('be.visible');
      cy.findByLabelText('02.08.2024').should('be.visible');
      cy.contains('10:34').should('be.visible');
      cy.contains('AM').should('be.visible');

      cy.makeSnapshot();
    });

    it('does not display the date and timezone when corresponding props are set', () => {
      initialize({
        displayType: 'clock',
        showDate: false,
        showTimezone: false,
        timeFormat: '12'
      });

      cy.contains('Europe/Helsinki').should('not.exist');
      cy.contains('02.08.2024').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays the time in the corresponding timezone and time format when corresponding props are set', () => {
      initialize({
        displayType: 'clock',
        locale: { id: 'fr-FR', name: 'French' },
        showDate: true,
        showTimezone: true,
        timeFormat: '24',
        timezone: { id: 'Europe/London', name: 'Europe/London' }
      });

      cy.contains('Europe/London').should('be.visible');
      cy.contains('02/08/2024').should('be.visible');
      cy.contains('08:34').should('be.visible');

      cy.makeSnapshot();
    });

    it('displays the widget with a custom background color and a description when corresponding props are set', () => {
      initialize({
        backgroundColor: '#456373',
        displayType: 'clock',
        hasDescription: true,
        locale: { id: 'fr-FR', name: 'French' },
        showDate: true,
        showTimezone: true,
        timeFormat: '24',
        timezone: { id: 'Europe/London', name: 'Europe/London' }
      });

      cy.contains('Europe/London').should('be.visible');
      cy.makeSnapshot();
    });
  });

  describe('Timer', () => {
    it('displays the widget with default properties', () => {
      initialize({
        countdown,
        displayType: 'timer',
        showDate: true,
        showTimezone: true
      });

      cy.contains('Europe/Helsinki').should('be.visible');
      cy.findByTestId('HourglassTopIcon').should('be.visible');
      cy.contains('Ends at: 02.08.2024 11:24').should('be.visible');
      cy.contains('00:50:00').should('be.visible');
      cy.contains('Hours').should('be.visible');
      cy.contains('Minutes').should('be.visible');
      cy.contains('Seconds').should('be.visible');

      cy.makeSnapshot();
    });

    it('does not display the date and timezone when corresponding props are set', () => {
      initialize({
        countdown,
        displayType: 'timer',
        showDate: false,
        showTimezone: false
      });

      cy.contains('Europe/Helsinki').should('not.exist');
      cy.contains('02.08.2024').should('not.exist');

      cy.makeSnapshot();
    });

    it('displays days when the ends date is far away', () => {
      initialize({
        countdown: farCountdown,
        displayType: 'timer',
        showDate: true,
        showTimezone: false
      });

      cy.contains('Ends at: 16.08.2024 20:42').should('be.visible');
      cy.contains('14:10:07').should('be.visible');
      cy.contains('Days').should('be.visible');
      cy.contains('Hours').should('be.visible');
      cy.contains('Minutes').should('be.visible');
      cy.contains('Seconds').should('not.exist');

      cy.makeSnapshot();
    });

    it('does not display remaining time when the countdown is invalid', () => {
      initialize({
        countdown: invalidCountdown,
        displayType: 'timer',
        locale: { id: 'en-GB', name: 'English' },
        showDate: true,
        showTimezone: false
      });

      cy.contains('Ends at: 01/01/1970 2:40 AM').should('be.visible');
      cy.contains('00:00:00').should('be.visible');

      cy.makeSnapshot();
    });
  });
});
