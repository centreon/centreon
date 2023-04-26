import { useEffect, useState } from 'react';

import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { find, propEq } from 'ramda';

import { getData, useRequest } from '@centreon/ui';

import Graph from './Graph';
import { adjustGraphData } from './helpers';
import { GraphData } from './models';

interface Graph {
  graphEndpoint: string;
}

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

const rootElement = document.getElementById('root');
rootElement.style.height = '80%';

const WrapperGraph = ({ graphEndpoint }: Graph): JSX.Element | null => {
  const [data, setData] = useState<any>();

  const { sendRequest: sendGetGraphDataRequest } = useRequest<GraphData>({
    request: getData
  });

  useEffect(() => {
    if (!graphEndpoint) {
      return;
    }
    sendGetGraphDataRequest({
      endpoint: graphEndpoint
    })
      .then((resp) => {
        const { timeSeries } = adjustGraphData(resp);
        const baseAxis = resp.global.base;
        const { title } = resp.global;

        const newLineData = adjustGraphData(resp).lines;

        if (data?.lines) {
          const newLines = newLineData.map((line) => ({
            ...line,
            display:
              find(propEq('name', line.name), data.lines)?.display ?? true
          }));

          setData({ ...data, baseAxis, lines: newLines, timeSeries, title });

          return;
        }

        setData({
          ...data,
          baseAxis,
          lines: newLineData,
          timeSeries,
          title
        });
      })
      .catch(() => undefined);
  }, [graphEndpoint]);

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
