import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { find, propEq } from 'ramda';

import { getData, useRequest } from '@centreon/ui';

import { ZoomParametersAtom } from './InteractionWithGraph/ZoomPreview/zoomPreviewAtoms';
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
  graphEndpoint: GraphEndpoint;
}

const useGraphData = ({ graphEndpoint }: Props): GraphDataResult => {
  const [data, setData] = useState<Data>();
  const { baseUrl, queryParameters } = graphEndpoint;

  const { sendRequest: sendGetGraphDataRequest } = useRequest<GraphData>({
    request: getData
  });

  const [zoomParameters, setZoomParameters] = useAtom(ZoomParametersAtom);

  const prepareData = (resp): void => {
    const { timeSeries } = adjustGraphData(resp);
    const baseAxis = resp.global.base;
    const { title } = resp.global;
    const endpoint = {
      baseUrl,
      queryParameters: (zoomParameters ?? queryParameters) as GraphParameters
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
    const { start, end } = queryParameters;

    return `${baseUrl}?start=${start}&end=${end}}`;
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
