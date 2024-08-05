import { equals, isNil } from 'ramda';

import { displayArea } from '../../../helpers/index';
import {
  PatternThreshold,
  ThresholdType,
  VariationThreshold
} from '../../../models';
import { TimeValue } from '../../../../common/timeSeries/models';

import BasicThreshold from './BasicThreshold';
import Circle from './Circle';
import ThresholdWithPatternLines from './ThresholdWithPatternLines';
import ThresholdWithVariation from './ThresholdWithVariation';
import { WrapperThresholdLinesModel } from './models';
import useScaleThreshold from './useScaleThreshold';

interface Props extends WrapperThresholdLinesModel {
  curve: 'linear' | 'natural' | 'step';
  graphHeight: number;
  timeSeries: Array<TimeValue>;
}

const WrapperThresholdLines = ({
  areaThresholdLines,
  graphHeight,
  lines,
  timeSeries,
  xScale,
  curve,
  yScalesPerUnit
}: Props): JSX.Element | null => {
  const data = useScaleThreshold({
    areaThresholdLines,
    lines,
    xScale,
    yScalesPerUnit
  });

  if (!data) {
    return null;
  }

  const { getX, getY0, getY1, lineColorY0, lineColorY1, ...rest } = data;

  const commonProps = {
    curve,
    fillAboveArea: lineColorY0,
    fillBelowArea: lineColorY1,
    getX,
    graphHeight,
    timeSeries
  };

  const thresholdLines = areaThresholdLines?.map((item, index) => {
    const { type, id } = item;

    if (equals(type, ThresholdType.basic)) {
      return [
        {
          Component: BasicThreshold,
          key: index,
          props: { ...commonProps, getY0, getY1, id }
        }
      ];
    }
    if (equals(type, ThresholdType.variation)) {
      const dataVariation = item as VariationThreshold;
      if (!rest?.getY0Variation || !rest.getY1Variation || !rest.getYOrigin) {
        return null;
      }

      return [
        {
          Component: ThresholdWithVariation,
          key: index,
          props: {
            factors: dataVariation.factors,
            ...commonProps,
            ...rest
          }
        },
        {
          Component: Circle,
          key: 'circle',
          props: {
            ...rest,
            getCountDisplayedCircles: dataVariation?.getCountDisplayedCircles,
            getX,
            timeSeries
          }
        }
      ];
    }
    if (equals(type, ThresholdType.pattern)) {
      const dataPattern = item as PatternThreshold;

      if (!displayArea(dataPattern?.data)) {
        return null;
      }

      const { data: pattern } = dataPattern;

      return pattern.map((element, ind) => ({
        Component: ThresholdWithPatternLines,
        key: ind,
        props: {
          data: element,
          graphHeight,
          key: ind,
          orientation: dataPattern?.orientation,
          xScale,
          yScalesPerUnit
        }
      }));
    }

    return null;
  });

  const filteredThresholdLines = thresholdLines?.filter((item) => !isNil(item));

  if (!filteredThresholdLines) {
    return null;
  }

  return (
    <g>
      {filteredThresholdLines.map((element) =>
        element?.map(({ Component, props, key }) => (
          <Component {...props} id={props?.id ?? key} key={key} />
        ))
      )}
    </g>
  );
};

export default WrapperThresholdLines;
