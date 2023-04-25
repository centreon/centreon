import { isEmpty, isNil } from 'ramda';

import { getStackedYScale, getUnits, getYScale } from '../timeSeries';
import { Line } from '../timeSeries/models';

import RegularLine from './RegularLines';
import StackedLines from './StackedLines';
import { Shape } from './models';

interface AnchorPoint {
  renderRegularLinesAnchorPoint: any;
  renderStackedAnchorPoint: any;
}

interface Props {
  anchorPoint: AnchorPoint;
  height: number;
  shape: Shape;
}

const Lines = ({ height, shape, anchorPoint }: Props): JSX.Element => {
  const { areaRegularLines, areaStackedLines } = shape;
  const { renderRegularLinesAnchorPoint, renderStackedAnchorPoint } =
    anchorPoint;
  const { regularStackedLinesData, invertedStackedLinesData } =
    areaStackedLines;

  const { lines: regularLines, timeSeries: regularLinesTimeSeries } =
    areaRegularLines;

  const {
    lines: regularStackedLines,
    timeSeries: regularStackedLinesTimeSeries
  } = regularStackedLinesData;

  const {
    lines: invertedStackedLines,
    timeSeries: invertedStackedLinesTimeSeries
  } = invertedStackedLinesData;

  const displayArea = (data: unknown): boolean =>
    !isEmpty(data) && !isNil(data);

  const displayAreaStackedLines =
    areaStackedLines.display && displayArea(regularStackedLines);

  const displayAreaInvertedStackedLines =
    areaStackedLines.display && displayArea(invertedStackedLines);

  const displayRegularLines =
    areaRegularLines.display && displayArea(regularLines);

  const stackedYScale = getStackedYScale({
    leftScale: areaStackedLines?.leftScale,
    rightScale: areaStackedLines?.rightScale
  });

  const leftScaleRegularLines = areaRegularLines?.leftScale;
  const rightScaleRegularLines = areaRegularLines?.rightScale;
  const xScaleRegularLines = areaRegularLines?.xScale;

  const commonStackedLinesProps = {
    xScale: areaStackedLines?.xScale,
    yScale: stackedYScale
  };

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

      {displayRegularLines
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
                    regularLinesTimeSeries,
                    transparency,
                    xScaleRegularLines,
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
