import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';

import type { ComponentType } from 'react';

import type { Tooltip, TimelineProps } from './models';
import ResponsiveTimeline from './Timeline';

// Its props type also asks for the children of ParentSize, which it provides.
const Timeline = ResponsiveTimeline as ComponentType<TimelineProps>;

export const timelineData = [
  {
    color: 'green',
    end: '2024-09-09T11:15:00+02:00',
    start: '2024-09-09T10:57:42+02:00'
  },
  {
    color: 'red',
    end: '2024-09-09T11:30:00+02:00',
    start: '2024-09-09T11:15:00+02:00'
  },
  {
    color: 'gray',
    end: '2024-09-09T11:45:00+02:00',
    start: '2024-09-09T11:30:00+02:00'
  },
  {
    color: 'green',
    end: '2024-09-09T12:00:00+02:00',
    start: '2024-09-09T11:45:00+02:00'
  },
  {
    color: 'red',
    end: '2024-09-09T12:20:00+02:00',
    start: '2024-09-09T12:00:00+02:00'
  },
  {
    color: 'gray',
    end: '2024-09-09T12:40:00+02:00',
    start: '2024-09-09T12:20:00+02:00'
  },
  {
    color: 'green',
    end: '2024-09-09T12:57:42+02:00',
    start: '2024-09-09T12:40:00+02:00'
  }
];

const TooltipContent = ({
  start,
  end,
  color,
  duration
}: Tooltip): JSX.Element => (
  <div
    data-testid="tooltip-content"
    style={{
      display: 'flex',
      flexDirection: 'column',
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

interface Props {
  customTooltip?: boolean;
}

export const Default = ({ customTooltip = false }: Props): JSX.Element => {
  const store = createStore();
  store.set(userAtom, { locale: 'en', timezone: 'Europe/Paris' } as never);

  return (
    <Provider store={store}>
      <div style={{ height: '100px', width: '70%' }}>
        <Timeline
          data={timelineData}
          endDate="2024-09-09T12:57:42+02:00"
          startDate="2024-09-09T10:57:42+02:00"
          TooltipContent={customTooltip ? TooltipContent : undefined}
        />
      </div>
    </Provider>
  );
};
