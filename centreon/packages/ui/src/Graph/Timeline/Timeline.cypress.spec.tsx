import Timeline from './Timeline';

const statuses = [
  {
    start: new Date('2024-09-09T10:57:42Z'),
    end: new Date('2024-09-09T11:15:00Z'),
    status: 'ok',
    color: 'green'
  },
  {
    start: new Date('2024-09-09T11:15:00Z'),
    end: new Date('2024-09-09T11:30:00Z'),
    status: 'down',
    color: 'red'
  },
  {
    start: new Date('2024-09-09T11:30:00Z'),
    end: new Date('2024-09-09T11:45:00Z'),
    status: 'unknown',
    color: 'gray'
  },
  {
    start: new Date('2024-09-09T11:45:00Z'),
    end: new Date('2024-09-09T12:00:00Z'),
    status: 'ok',
    color: 'green'
  },
  {
    start: new Date('2024-09-09T12:00:00Z'),
    end: new Date('2024-09-09T12:20:00Z'),
    status: 'down',
    color: 'red'
  },
  {
    start: new Date('2024-09-09T12:20:00Z'),
    end: new Date('2024-09-09T12:40:00Z'),
    status: 'unknown',
    color: 'gray'
  },
  {
    start: new Date('2024-09-09T12:40:00Z'),
    end: new Date('2024-09-09T12:57:42Z'),
    status: 'ok',
    color: 'green'
  }
];

const startDate = '2024-09-09T10:57:42Z';
const endDate = '2024-09-09T12:57:42Z';

const initialize = (): void => {
  cy.mount({
    Component: (
      <div
        style={{
          height: '200px',
          width: '100%',
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center'
        }}
      >
        <Timeline
          statuses={statuses}
          start_date={startDate}
          end_date={endDate}
        />
      </div>
    )
  });
};

describe('Timeline', () => {
  it('renders timeline chart correctly with provided data', () => {
    initialize();

    cy.makeSnapshot();
  });
});
