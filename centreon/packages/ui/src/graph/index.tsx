import 'dayjs/locale/en';
import 'dayjs/locale/pt';
import 'dayjs/locale/fr';
import 'dayjs/locale/es';
import dayjs from 'dayjs';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import { Responsive } from '@visx/visx';

import Graph from './Graph';

interface Graph {
  graphData: any;
}

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

const rootElement = document.getElementById('root');
rootElement?.style.height = '100%';

const WrapperGraph = ({ graphData }: Graph): JSX.Element => {
  return (
    <div style={{ height: '100%', width: '100%' }}>
      <Responsive.ParentSize>
        {({ height, width }): JSX.Element => (
          <Graph graphData={graphData} height={height} width={width} />
        )}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
