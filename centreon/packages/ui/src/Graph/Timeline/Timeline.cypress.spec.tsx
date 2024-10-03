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

const initialize = (): void => {
  cy.mount({
    Component: (
      <Provider store={store}>
        <div
          style={{
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            height: '70vh',
            width: '100%'
          }}
        >
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
              TooltipContent={TooltipContent}
            />
          </div>
        </div>
      </Provider>
    )
  });
};

describe('Timeline', () => {
  beforeEach(initialize);

  it('checks that the correct number of bars are rendered', () => {
    cy.get('rect').should('have.length', data.length);

    cy.makeSnapshot();
  });

  it('checks that each bar has the correct color', () => {
    data.forEach(({ color }, index) => {
      cy.get('rect').eq(index).should('have.attr', 'fill', color);
    });
  });

  it('displays tooltip with correct information when hovered over a bar', () => {
    cy.get('rect').first().trigger('mouseover');

    cy.get('[data-testid="tooltip-content"]').within(() => {
      cy.contains('09/09/2024 10:57 AM');
      cy.contains('09/09/2024 11:15 AM');
      cy.contains('green');
      cy.contains('17 minutes');
    });

    cy.makeSnapshot();
  });

  it('displays correct tick labels on the x-axis', () => {
    cy.get('.visx-axis-bottom .visx-axis-tick').first().contains('11:00');
  });
});
