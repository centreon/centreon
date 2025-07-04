import { userAtom } from '@centreon/ui-context';
import { Provider, createStore } from 'jotai';
import Timeline from './Timeline';
import { Tooltip } from './models';

const data = [
  {
    start: '2024-09-09T10:57:42+02:00',
    end: '2024-09-09T11:15:00+02:00',
    color: 'green'
  },
  {
    start: '2024-09-09T11:15:00+02:00',
    end: '2024-09-09T11:30:00+02:00',
    color: 'red'
  },
  {
    start: '2024-09-09T11:30:00+02:00',
    end: '2024-09-09T11:45:00+02:00',
    color: 'gray'
  },
  {
    start: '2024-09-09T11:45:00+02:00',
    end: '2024-09-09T12:00:00+02:00',
    color: 'green'
  },
  {
    start: '2024-09-09T12:00:00+02:00',
    end: '2024-09-09T12:20:00+02:00',
    color: 'red'
  },
  {
    start: '2024-09-09T12:20:00+02:00',
    end: '2024-09-09T12:40:00+02:00',
    color: 'gray'
  },
  {
    start: '2024-09-09T12:40:00+02:00',
    end: '2024-09-09T12:57:42+02:00',
    color: 'green'
  }
];

const startDate = '2024-09-09T10:57:42+02:00';
const endDate = '2024-09-09T12:57:42+02:00';

const TooltipContent = ({ start, end, color, duration }: Tooltip) => (
  <div
    data-testid="tooltip-content"
    style={{
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      alignItems: 'center',
      gap: '10px',
      padding: '5px'
    }}
  >
    <span>{start}</span>
    <span>{end}</span>
    <span>{color}</span>
    <span>{duration}</span>
  </div>
);

const store = createStore();
store.set(userAtom, { timezone: 'Europe/Paris', locale: 'en' });

const initialize = (displayDefaultTooltip = true): void => {
  cy.mount({
    Component: (
      <Provider store={store}>
        <div
          style={{
            height: '100px',
            width: '70%'
          }}
        >
          <Timeline
            data={data}
            startDate={startDate}
            endDate={endDate}
            TooltipContent={displayDefaultTooltip ? undefined : TooltipContent}
          />
        </div>
      </Provider>
    )
  });
};

describe('Timeline', () => {
  it('checks that the correct number of bars are rendered', () => {
    initialize();

    cy.get('path').should('have.length', data.length);

    cy.makeSnapshot();
  });

  it('checks that each bar has the correct color', () => {
    initialize();

    data.forEach(({ color }, index) => {
      cy.get('path').eq(index).should('have.attr', 'fill', color);
    });
  });

  it('displays tooltip with correct information when hovered over a bar', () => {
    initialize(false);

    cy.get('path').first().trigger('mouseover');

    cy.get('[data-testid="tooltip-content"]').within(() => {
      cy.contains('09/09/2024 10:57 AM').should('be.visible');
      cy.contains('09/09/2024 11:15 AM').should('be.visible');
      cy.contains('green').should('be.visible');
      cy.contains('17 minutes').should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('displays the default tooltip with correct information when hovered over a bar', () => {
    initialize();

    cy.get('path').first().trigger('mouseover');

    cy.get('[role="tooltip"]').within(() => {
      cy.contains('09/09/2024 10:57 AM')
        .should('be.visible')
        .and('have.css', 'color', 'rgb(0, 128, 0)');
      cy.contains('09/09/2024 11:15 AM')
        .should('be.visible')
        .and('have.css', 'color', 'rgb(0, 128, 0)');
      cy.contains('17 minutes')
        .should('be.visible')
        .and('have.css', 'color', 'rgb(0, 128, 0)');
    });

    cy.makeSnapshot();
  });

  it('displays correct tick labels on the x-axis', () => {
    initialize();

    cy.get('.visx-axis-bottom .visx-axis-tick').first().contains('11:00');
  });
});
