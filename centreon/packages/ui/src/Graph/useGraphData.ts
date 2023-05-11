import { useEffect, useState } from 'react';

import { prop, sortBy } from 'ramda';

import { adjustGraphData } from './helpers';
import { Data, GraphData } from './models';

interface GraphDataResult {
  adjustedData?: Data;
}

interface Props {
  data: GraphData;
  end?: string;
  start?: string;
}

const useGraphData = ({ data, end, start }: Props): GraphDataResult => {
  const [adjustedData, setAdjustedData] = useState<Data>();

  const prepareData = (dataToAdjust: GraphData): void => {
    const { timeSeries } = adjustGraphData(dataToAdjust);
    const baseAxis = dataToAdjust.global.base;
    const { title } = dataToAdjust.global;

    const newLineData = adjustGraphData(dataToAdjust).lines;
    const sortedLines = sortBy(prop('name'), newLineData);

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
