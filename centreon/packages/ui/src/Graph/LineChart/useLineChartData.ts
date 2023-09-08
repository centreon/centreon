import { useEffect, useState } from 'react';

import { compose, prop, sortBy, toLower } from 'ramda';

import { LineChartData } from '../common/models';

import { adjustGraphData } from './helpers';
import { Data } from './models';

interface GraphDataResult {
  adjustedData?: Data;
}

interface Props {
  data?: LineChartData;
  end?: string;
  start?: string;
}

const useGraphData = ({ data, end, start }: Props): GraphDataResult => {
  const [adjustedData, setAdjustedData] = useState<Data>();

  const prepareData = (dataToAdjust: LineChartData): void => {
    const { timeSeries } = adjustGraphData(dataToAdjust);
    const baseAxis = dataToAdjust.global.base;
    const { title } = dataToAdjust.global;

    const newLineData = adjustGraphData(dataToAdjust).lines;
    const sortedLines = sortBy(compose(toLower, prop('name')), newLineData);

    setAdjustedData({
      baseAxis,
      lines: sortedLines,
      timeSeries,
      title
    });
  };

  useEffect(() => {
    if (!data) {
      return;
    }
    prepareData(data);
  }, [end, start, data]);

  return { adjustedData };
};

export default useGraphData;
