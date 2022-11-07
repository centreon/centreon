<<<<<<< HEAD
=======
import * as React from 'react';

>>>>>>> centreon/dev-21.10.x
import { ScaleTime } from 'd3-scale';

import { TimelineEvent } from '../../../../Details/tabs/Timeline/models';

import CommentAnnotations from './Line/Comments';
import AcknowledgementAnnotations from './Line/Acknowledgement';
import DowntimeAnnotations from './Area/Downtime';

export interface Props {
  graphHeight: number;
<<<<<<< HEAD
  resourceId: string;
=======
>>>>>>> centreon/dev-21.10.x
  timeline: Array<TimelineEvent>;
  xScale: ScaleTime<number, number>;
}

<<<<<<< HEAD
const Annotations = ({
  xScale,
  timeline,
  graphHeight,
  resourceId,
}: Props): JSX.Element => {
  const props = {
    graphHeight,
    resourceId,
=======
const Annotations = ({ xScale, timeline, graphHeight }: Props): JSX.Element => {
  const props = {
    graphHeight,
>>>>>>> centreon/dev-21.10.x
    timeline,
    xScale,
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
