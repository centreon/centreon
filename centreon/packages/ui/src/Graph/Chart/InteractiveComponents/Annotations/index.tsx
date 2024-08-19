import { MutableRefObject } from 'react';

import { ScaleTime } from 'd3-scale';

import DowntimeAnnotations from './Area/Downtime';
import AcknowledgementAnnotations from './Line/Acknowledgement';
import CommentAnnotations from './Line/Comments';
import { TimelineEvent } from './models';
import useAnnotation from './useAnnotation';

export interface Props {
  data: Array<TimelineEvent>;
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
}

const Annotations = ({
  xScale,
  data,
  graphHeight,
  graphWidth,
  graphSvgRef
}: Props): JSX.Element => {
  const annotationHoveredId = useAnnotation({
    data,
    graphSvgRef,
    graphWidth,
    xScale
  });

  const props = {
    annotationHoveredId,
    data,
    graphHeight,
    xScale
  };

  return (
    <g>
      <CommentAnnotations {...props} />
      <AcknowledgementAnnotations {...props} />
      <DowntimeAnnotations {...props} />
    </g>
  );
};

export default Annotations;
