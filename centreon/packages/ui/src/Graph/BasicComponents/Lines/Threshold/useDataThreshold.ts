import { equals, isNil } from 'ramda';
import { ScaleLinear } from 'd3-scale';

import {
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from '../../../helpers/index';
import { getUnits, getYScale } from '../../../timeSeries/index';
import { Line } from '../../../timeSeries/models';

import { Data } from './models';

interface DataThreshold {
  dataY0?: Data;
  dataY1?: Data;
  dataYOrigin?: Data;
  displayThreshold: boolean;
}

interface Props {
  display: boolean;
  leftScale: ScaleLinear<number, number>;
  lines: Array<Line>;
  rightScale: ScaleLinear<number, number>;
}

const useDataThreshold = (areaThreshold: Props): DataThreshold => {
  const { lines, leftScale, rightScale, display } = areaThreshold;

  const dataMetricUpper = lines.find((line) =>
    equals(line.name, upperLineName)
  );

  const dataMetricLower = lines.find((line) =>
    equals(line.name, lowerLineName)
  );

  const dataMetricOrigin = findLineOfOriginMetricThreshold(lines);

  const displayThreshold =
    !isNil(dataMetricLower) &&
    !isNil(dataMetricUpper) &&
    display &&
    !isNil(dataMetricOrigin[0]);

  if (!displayThreshold) {
    return {
      dataY0: undefined,
      dataY1: undefined,
      dataYOrigin: undefined,
      displayThreshold
    };
  }

  const [, secondUnit, thirdUnit] = getUnits(lines);

  const {
    metric: metricY1,
    unit: unitY1,
    invert: invertY1,
    lineColor: lineColorY1
  } = dataMetricUpper;

  const {
    metric: metricY0,
    unit: unitY0,
    invert: invertY0,
    lineColor: lineColorY0
  } = dataMetricLower;

  const {
    metric: metricOrigin,
    unit: unitYOrigin,
    invert: invertYOrigin,
    lineColor: lineColorYOrigin
  } = dataMetricOrigin[0];

  const y1Scale = getYScale({
    hasMoreThanTwoUnits: !isNil(thirdUnit),
    invert: invertY1,
    leftScale,
    rightScale,
    secondUnit,
    unit: unitY1
  });

  const y0Scale = getYScale({
    hasMoreThanTwoUnits: !isNil(thirdUnit),
    invert: invertY0,
    leftScale,
    rightScale,
    secondUnit,
    unit: unitY0
  });

  const yScale = getYScale({
    hasMoreThanTwoUnits: !isNil(thirdUnit),
    invert: invertYOrigin,
    leftScale,
    rightScale,
    secondUnit,
    unit: unitYOrigin
  });

  return {
    dataY0: { lineColor: lineColorY0, metric: metricY0, yScale: y0Scale },
    dataY1: { lineColor: lineColorY1, metric: metricY1, yScale: y1Scale },
    dataYOrigin: { lineColor: lineColorYOrigin, metric: metricOrigin, yScale },
    displayThreshold
  };
};

export default useDataThreshold;
