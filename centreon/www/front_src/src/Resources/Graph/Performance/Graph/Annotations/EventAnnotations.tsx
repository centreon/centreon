<<<<<<< HEAD
=======
import * as React from 'react';

>>>>>>> centreon/dev-21.10.x
import { filter, propEq, isNil } from 'ramda';
import { ScaleTime } from 'd3-scale';

import { TimelineEvent } from '../../../../Details/tabs/Timeline/models';

import LineAnnotation from './Annotation/Line';
import AreaAnnotation from './Annotation/Area';

interface Props {
  Icon: (props) => JSX.Element;
  ariaLabel: string;
  color: string;
  graphHeight: number;
<<<<<<< HEAD
  resourceId: string;
=======
>>>>>>> centreon/dev-21.10.x
  timeline: Array<TimelineEvent>;
  type: string;
  xScale: ScaleTime<number, number>;
}

const EventAnnotations = ({
  type,
  xScale,
  timeline,
  graphHeight,
  Icon,
  ariaLabel,
  color,
<<<<<<< HEAD
  resourceId,
}: Props): JSX.Element => {
  const events = filter(propEq('type', type), timeline);
=======
}: Props): JSX.Element => {
  const events = filter<TimelineEvent>(propEq('type', type), timeline);
>>>>>>> centreon/dev-21.10.x

  return (
    <>
      {events.map((event) => {
        const props = {
          Icon,
          ariaLabel,
          color,
          event,
          graphHeight,
<<<<<<< HEAD
          resourceId,
=======
>>>>>>> centreon/dev-21.10.x
          xScale,
        };

        if (isNil(event.startDate) && isNil(event.endDate)) {
          return <LineAnnotation date={event.date} key={event.id} {...props} />;
        }

        return (
          <AreaAnnotation
            endDate={event.endDate as string}
            key={event.id}
            startDate={event.startDate as string}
            {...props}
          />
        );
      })}
    </>
  );
};

export default EventAnnotations;
