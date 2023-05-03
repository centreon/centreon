import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { find, propEq } from 'ramda';

import { getData, useRequest } from '@centreon/ui';

import { zoomParametersAtom } from './InteractionWithGraph/ZoomPreview/zoomPreviewAtoms';
import { adjustGraphData } from './helpers';
import { GraphData, GraphEndpoint, GraphParameters } from './models';
import { Line, TimeValue } from './timeSeries/models';

interface Data {
  baseAxis: number;
  endpoint: GraphEndpoint;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  title: string;
}

interface GraphDataResult {
  data?: Data;
}
interface Props {
  baseUrl: string;
  end: any;
  start: any;
}

const useGraphData = ({ baseUrl, start, end }: Props): GraphDataResult => {
  const [data, setData] = useState<Data>();
  const [graphEndpoint, setGraphEndpoint] = useState<GraphEndpoint | null>(
    null
  );

  const { sendRequest: sendGetGraphDataRequest } = useRequest<GraphData>({
    request: getData
  });

  const [zoomParameters, setZoomParameters] = useAtom(zoomParametersAtom);

  const prepareData = (resp): void => {
    const { timeSeries } = adjustGraphData(resp);
    const baseAxis = resp.global.base;
    const { title } = resp.global;
    const endpoint = {
      baseUrl,
      queryParameters: (zoomParameters ??
        graphEndpoint?.queryParameters) as GraphParameters
    };

    const newLineData = adjustGraphData(resp).lines;

    if (data?.lines) {
      const newLines = newLineData.map((line) => ({
        ...line,
        display: find(propEq('name', line.name), data.lines)?.display ?? true
      }));

      setData({
        baseAxis,
        endpoint,
        lines: newLines,
        timeSeries,
        title
      });

      return;
    }

    setData({
      baseAxis,
      endpoint,
      lines: newLineData,
      timeSeries,
      title
    });
  };

  const getEndpoint = (): string => {
    const { queryParameters } = graphEndpoint as GraphEndpoint;

    return `${baseUrl}?start=${queryParameters.start}&end=${queryParameters.end}`;
  };

  const updateEndpoint = (): string => {
    if (!zoomParameters || !graphEndpoint) {
      return '';
    }
    const { start: startDate, end: endDate } = zoomParameters;
    const parameters = `?start=${startDate}&end=${endDate}`;

    return `${baseUrl}${parameters}`;
  };

  useEffect(() => {
    if (!end || !start || !baseUrl) {
      return;
    }
    setGraphEndpoint({
      baseUrl,
      queryParameters: {
        end: new Date(end).toISOString(),
        start: new Date(start).toISOString()
      }
    });
  }, [baseUrl, start, end]);

  useEffect(() => {
    if (!graphEndpoint) {
      return;
    }

    sendGetGraphDataRequest({
      endpoint: getEndpoint()
    })
      .then((resp) => {
        prepareData(resp);
      })
      .catch(() => undefined);
  }, [graphEndpoint]);

  useEffect(() => {
    const endpoint = updateEndpoint();
    if (!zoomParameters || !endpoint) {
      return;
    }

    sendGetGraphDataRequest({
      endpoint
    })
      .then((resp) => {
        prepareData(resp);
        setZoomParameters(null);
      })
      .catch(() => undefined);
  }, [zoomParameters]);

  return { data };
};

export default useGraphData;
