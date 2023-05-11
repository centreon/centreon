import { ReactNode } from 'react';

import { isNil } from 'ramda';

import {
  RegularLinesAnchorPoint,
  StackedAnchorPoint
} from '../../IntercatifsComponents/AnchorPoint/models';
import { getUnits, getYScale } from '../../timeSeries';
import { Line } from '../../timeSeries/models';

import RegularLine from './RegularLines';
import StackedLines from './StackedLines';
import ThresholdLines from './Threshold';
import { Shape } from './models';
import useDataRegularLines from './useDataRegularLines';
import useDataStackedLines from './useDataStackLines';
import useDataThreshold from './useDataThreshold';
import { Data } from './Threshold/models';

interface AnchorPoint {
  renderRegularLinesAnchorPoint: (args: RegularLinesAnchorPoint) => ReactNode;
  renderStackedAnchorPoint: (args: StackedAnchorPoint) => ReactNode;
}

interface Props {
  anchorPoint: AnchorPoint;
  height: number;
  shape: Shape;
}

const Lines = ({ height, shape, anchorPoint }: Props): JSX.Element => {
  const { areaRegularLines, areaStackedLines, areaThreshold } = shape;
  const { renderRegularLinesAnchorPoint, renderStackedAnchorPoint } =
    anchorPoint;
  const { lines, leftScale, rightScale, timeSeries, xScale } = areaThreshold;

  const {
    displayAreaInvertedStackedLines,
    displayAreaStackedLines,
    invertedStackedLines,
    invertedStackedLinesTimeSeries,
    regularStackedLines,
    regularStackedLinesTimeSeries,
    xScaleStackedLines,
    yScaleStackedLines
  } = useDataStackedLines(areaStackedLines);

  const {
    display: displayAreaRegularLines,
    leftScale: leftScaleRegularLines,
    timeSeries: regularLinesTimeSeries,
    rightScale: rightScaleRegularLines,
    xScale: xScaleRegularLines,
    lines: regularLines
  } = useDataRegularLines(areaRegularLines);

  const { dataY0, dataY1, displayThreshold } = useDataThreshold({
    leftScale,
    lines,
    rightScale
  });

  const commonStackedLinesProps = { xScaleStackedLines, yScaleStackedLines };

  return (
    <g>
      {displayAreaStackedLines && (
        <StackedLines
          lines={regularStackedLines}
          renderStackedAnchorPoint={renderStackedAnchorPoint}
          timeSeries={regularStackedLinesTimeSeries}
          {...commonStackedLinesProps}
        />
      )}
      {displayAreaInvertedStackedLines && (
        <StackedLines
          lines={invertedStackedLines}
          renderStackedAnchorPoint={renderStackedAnchorPoint}
          timeSeries={invertedStackedLinesTimeSeries}
          {...commonStackedLinesProps}
        />
      )}

      {displayThreshold && (
        <ThresholdLines
          dataY0={dataY0 as Data}
          dataY1={dataY1 as Data}
          graphHeight={height}
          timeSeries={timeSeries}
          xScale={xScale}
        />
      )}

      {displayAreaRegularLines
        ? regularLines.map(
            ({
              metric,
              areaColor,
              transparency,
              lineColor,
              filled,
              unit,
              highlight,
              invert
            }) => {
              const [, secondUnit, thirdUnit] = getUnits(
                regularLines as Array<Line>
              );
              const yScale = getYScale({
                hasMoreThanTwoUnits: !isNil(thirdUnit),
                invert,
                leftScale: leftScaleRegularLines,
                rightScale: rightScaleRegularLines,
                secondUnit,
                unit
              });

              return (
                <g key={metric}>
                  {renderRegularLinesAnchorPoint?.({
                    areaColor,
                    lineColor,
                    metric,
                    timeSeries: regularLinesTimeSeries,
                    transparency,
                    xScale: xScaleRegularLines,
                    yScale
                  })}
                  <RegularLine
                    areaColor={areaColor}
                    filled={filled}
                    graphHeight={height}
                    highlight={highlight}
                    lineColor={lineColor}
                    metric={metric}
                    timeSeries={regularLinesTimeSeries}
                    transparency={transparency}
                    unit={unit}
                    xScale={xScaleRegularLines}
                    yScale={yScale}
                  />
                </g>
              );
            }
          )
        : null}
    </g>
  );
};

export default Lines;
