import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';

import Graph from './Graph';
import { linesGraphAtom } from './graphAtoms';
import { GlobalAreaLines, GraphData, GraphProps } from './models';
import useGraphData from './useGraphData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<GraphProps> {
  data: GraphData;
  end: string;
  loading: boolean;
  shapeLines?: GlobalAreaLines;
  start: string;
}

const WrapperGraph = ({
  end,
  start,
  height,
  width,
  shapeLines,
  axis,
  anchorPoint,
  zoomPreview,
  data,
  loading,
  timeShiftZones,
  tooltip,
  annotationEvent
}: Props): JSX.Element | null => {
  const { adjustedData } = useGraphData({ data, end, start });
  const lines = useAtomValue(linesGraphAtom);

  if (!adjustedData || !lines) {
    return null;
  }

  return (
    <div>
      <Responsive.ParentSize>
        {({
          height: responsiveHeight,
          width: responsiveWidth
        }): JSX.Element => (
          <Graph
            anchorPoint={anchorPoint}
            annotationEvent={annotationEvent}
            axis={axis}
            graphData={{ ...adjustedData, lines }}
            graphInterval={{ end, start }}
            height={height ?? responsiveHeight}
            loading={loading}
            shapeLines={shapeLines}
            timeShiftZones={timeShiftZones}
            tooltip={tooltip}
            width={width ?? responsiveWidth}
            zoomPreview={zoomPreview}
          />
        )}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
