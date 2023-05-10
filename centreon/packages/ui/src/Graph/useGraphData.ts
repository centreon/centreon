import { useEffect, useState } from 'react';

import { useAtom } from 'jotai';
import { prop, sortBy } from 'ramda';

import { linesGraphAtom } from './graphAtoms';
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
  const [linesGraph, setLinesGraph] = useAtom(linesGraphAtom);

  const prepareData = (dataToAdjust: GraphData): void => {
    const { timeSeries } = adjustGraphData(dataToAdjust);
    const baseAxis = dataToAdjust.global.base;
    const { title } = dataToAdjust.global;

    const newLineData = adjustGraphData(dataToAdjust).lines;
    const sortedLines = sortBy(prop('name'), newLineData);
    setLinesGraph(sortedLines);

    setAdjustedData({
      baseAxis,
      timeSeries,
      title
    } as Data);
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
