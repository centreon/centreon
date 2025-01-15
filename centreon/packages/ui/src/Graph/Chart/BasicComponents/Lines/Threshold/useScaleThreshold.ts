import { equals, isNil, prop } from 'ramda';

import { getTime, getYScale } from '../../../../common/timeSeries';
import { TimeValue } from '../../../../common/timeSeries/models';
import { displayArea } from '../../../helpers/index';
import { ThresholdType, VariationThreshold } from '../../../models';

import { envelopeVariationFormula } from './helpers';
import {
  LinesThreshold,
  ScaleVariationThreshold,
  WrapperThresholdLinesModel,
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from './models';

interface Result extends Partial<ScaleVariationThreshold> {
  getX: (timeValue: TimeValue) => number;
  getY0: (timeValue: TimeValue) => number;
  getY1: (timeValue: TimeValue) => number;
  lineColorY0: string;
  lineColorY1: string;
}

const useScaleThreshold = ({
  lines,
  areaThresholdLines,
  yScalesPerUnit,
  xScale
}: WrapperThresholdLinesModel): Result | null => {
  const getLinesThreshold = (): LinesThreshold | null => {
    const lineUpper = lines.find((line) => equals(line.name, upperLineName));

    const lineLower = lines.find((line) => equals(line.name, lowerLineName));

    const lineOrigin = findLineOfOriginMetricThreshold(lines)[0];
    if (!lineLower || !lineOrigin || !lineUpper) {
      return null;
    }

    return { lineLower, lineOrigin, lineUpper };
  };

  const linesThreshold = getLinesThreshold();
  if (!linesThreshold) {
    return null;
  }

  const { lineUpper, lineLower, lineOrigin } = linesThreshold;

  const {
    metric_id: metricY1,
    unit: unitY1,
    invert: invertY1,
    lineColor: lineColorY1
  } = lineUpper;

  const {
    metric_id: metricY0,
    unit: unitY0,
    invert: invertY0,
    lineColor: lineColorY0
  } = lineLower;

  const {
    metric_id: metricOrigin,
    unit: unitYOrigin,
    invert: invertYOrigin
  } = lineOrigin;

  const y1Scale = getYScale({
    invert: invertY1,
    unit: unitY1,
    yScalesPerUnit
  });

  const y0Scale = getYScale({
    invert: invertY0,
    unit: unitY0,
    yScalesPerUnit
  });

  const yScale = getYScale({
    invert: invertYOrigin,
    unit: unitYOrigin,
    yScalesPerUnit
  });

  const getX = (timeValue: TimeValue): number => {
    return xScale(getTime(timeValue)) ?? null;
  };

  const getY0 = (timeValue: TimeValue): number => {
    return y0Scale(prop(metricY0, timeValue)) ?? null;
  };
  const getY1 = (timeValue: TimeValue): number => {
    return y1Scale(prop(metricY1, timeValue)) ?? null;
  };

  const commonResult = { getX, getY0, getY1, lineColorY0, lineColorY1 };

  const isVariationType = !!areaThresholdLines?.find(
    (item) => item && equals(item.type, ThresholdType.variation)
  );
  if (isVariationType) {
    const data = areaThresholdLines?.find((item) =>
      equals(item.type, ThresholdType.variation) ? item : null
    ) as VariationThreshold;

    const variationFactorsExist =
      displayArea(data?.factors) &&
      !isNil(data?.factors?.simulatedFactorMultiplication) &&
      !isNil(data?.factors?.currentFactorMultiplication);

    if (!variationFactorsExist) {
      return commonResult;
    }

    const getY0Variation = (timeValue: TimeValue): number => {
      const upperRealValue = prop(metricY1, timeValue);
      const lowerRealValue = prop(metricY0, timeValue);

      const variation = envelopeVariationFormula({
        factorsData: data.factors,
        lowerRealValue,
        upperRealValue
      });

      return y0Scale(prop(metricY0, timeValue) + variation) ?? null;
    };

    const getY1Variation = (timeValue: TimeValue): number => {
      const upperRealValue = prop(metricY1, timeValue);
      const lowerRealValue = prop(metricY0, timeValue);

      const variation = envelopeVariationFormula({
        factorsData: data.factors,
        lowerRealValue,
        upperRealValue
      });

      return y1Scale(prop(metricY1, timeValue) - variation) ?? null;
    };
    const getYOrigin = (timeValue: TimeValue): number =>
      yScale(prop(metricOrigin, timeValue));

    return {
      getY0Variation,
      getY1Variation,
      getYOrigin,
      ...commonResult
    };
  }

  return commonResult;
};

export default useScaleThreshold;
