import { ScaleTime } from 'd3-scale';
import { filter, isNil, propEq } from 'ramda';

import AreaAnnotation from './Annotation/Area';
import LineAnnotation from './Annotation/Line';
import { TimelineEvent } from './models';

interface Props {
  Icon: (props) => JSX.Element | null;
  annotationHoveredId: number;
  ariaLabel: string;
  color: string;
  data: Array<TimelineEvent>;
  graphHeight: number;
  type: string;
  xScale: ScaleTime<number, number>;
}

const EventAnnotations = ({
  type,
  xScale,
  data,
  graphHeight,
  Icon,
  ariaLabel,
  color,
  annotationHoveredId
}: Props): JSX.Element => {
  const events = filter(propEq(type, 'type'), data);

  return (
    <>
      {events.map((event) => {
        const props = {
          Icon,
          annotationHoveredId,
          ariaLabel,
          color,
          event,
          graphHeight,
          xScale
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
