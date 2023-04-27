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
import { GraphEndpoint } from './models';
import useGraphData from './useGraphData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

const rootElement = document.getElementById('root');
rootElement.style.height = '80%';

interface Props {
  graphEndpoint: GraphEndpoint;
}

const WrapperGraph = ({ graphEndpoint }: Props): JSX.Element | null => {
  const { data } = useGraphData({ graphEndpoint });

  if (!data) {
    return null;
  }

  return (
    <div style={{ height: '100%', width: '100%' }}>
      <Responsive.ParentSize>
        {({ height, width }): JSX.Element => (
          <Graph graphData={data} height={height} width={width} />
        )}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
