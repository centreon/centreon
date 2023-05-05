import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import Graph from './Graph';
import { GlobalAreaLines, GraphProps } from './models';
import useGraphData from './useGraphData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends GraphProps {
  baseUrl: string;
  end: string;
  shapeLines: GlobalAreaLines;
  start: string;
}

const WrapperGraph = ({
  baseUrl,
  end,
  start,
  height,
  width,
  shapeLines,
  axis,
  grids,
  anchorPoint,
  zoomPreview
}: Props): JSX.Element | null => {
  const { data, loading } = useGraphData({ baseUrl, end, start });

  if (!data) {
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
            axis={axis}
            graphData={data}
            grids={grids}
            height={height ?? responsiveHeight}
            loading={loading}
            shapeLines={shapeLines}
            width={width ?? responsiveWidth}
            zoomPreview={zoomPreview}
          />
        )}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
