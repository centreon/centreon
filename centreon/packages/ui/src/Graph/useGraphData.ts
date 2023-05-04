import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { find, propEq } from 'ramda';

import { getData, useRequest } from '@centreon/ui';

import { zoomParametersAtom } from './InteractionWithGraph/ZoomPreview/zoomPreviewAtoms';
import { adjustGraphData } from './helpers';
import { Data, GraphData, GraphParameters } from './models';

interface QueryParameter {
  endTime: string;
  startTime: string;
}

interface GraphDataResult {
  data?: Data;
  loading: boolean;
}
interface Props extends GraphParameters {
  baseUrl: string;
}

const useGraphData = ({ baseUrl, start, end }: Props): GraphDataResult => {
  const [data, setData] = useState<Data>();

  const { sendRequest: sendGetGraphDataRequest, sending: loading } =
    useRequest<GraphData>({
      request: getData
    });

  const [zoomParameters, setZoomParameters] = useAtom(zoomParametersAtom);

  const prepareData = ({ response, queryParameters }): void => {
    const { timeSeries } = adjustGraphData(response);
    const baseAxis = response.global.base;
    const { title } = response.global;

    const newLineData = adjustGraphData(response).lines;

    if (response?.lines) {
      const newLines = newLineData.map((line) => ({
        ...line,
        display:
          find(propEq('name', line.name), response.lines)?.display ?? true
      }));

      setData({
        baseAxis,
        lines: newLines,
        queryParameters,
        timeSeries,
        title
      });

      return;
    }

    setData({
      baseAxis,
      lines: newLineData,
      queryParameters,
      timeSeries,
      title
    });
  };

  const getQueryParameters = ({ startTime, endTime }: QueryParameter): string =>
    `?start=${startTime}&end=${endTime}`;

  const getParameters = (): GraphParameters => {
    const beginning = zoomParameters ? zoomParameters.start : start;
    const ending = zoomParameters ? zoomParameters.end : end;

    return { end: ending, start: beginning };
  };

  const queryParameters = zoomParameters
    ? getQueryParameters({
        endTime: zoomParameters.end,
        startTime: zoomParameters.start
      })
    : getQueryParameters({ endTime: end, startTime: start });
  useEffect(() => {
    if (!end || !start || !baseUrl) {
      return;
    }

    const endpoint = `${baseUrl}${queryParameters}`;

    sendGetGraphDataRequest({
      endpoint
    }).then((response) => {
      prepareData({ queryParameters: getParameters(), response });
    });
  }, [baseUrl, start, end]);

  useEffect(() => {
    if (!zoomParameters) {
      return;
    }

    const endpoint = `${baseUrl}${queryParameters}`;

    sendGetGraphDataRequest({
      endpoint
    }).then((response) => {
      prepareData({ queryParameters: getParameters(), response });
      setZoomParameters(null);
    });
  }, [zoomParameters]);

  return { data, loading };
};

export default useGraphData;
