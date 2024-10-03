import { ScaleTime } from 'd3-scale';

import { TimelineEvent } from '../../../../Details/tabs/Timeline/models';

import DowntimeAnnotations from './Area/Downtime';
import AcknowledgementAnnotations from './Line/Acknowledgement';
import CommentAnnotations from './Line/Comments';

export interface Props {
  graphHeight: number;
  resourceId: string;
  timeline: Array<TimelineEvent>;
  xScale: ScaleTime<number, number>;
}

const Annotations = ({
  xScale,
  timeline,
  graphHeight,
  resourceId
}: Props): JSX.Element => {
  const props = {
    graphHeight,
    resourceId,
    timeline,
    xScale
  };

  return (
    <>
      <CommentAnnotations {...props} />
      <AcknowledgementAnnotations {...props} />
      <DowntimeAnnotations {...props} />
    </>
  );
};

export default Annotations;
