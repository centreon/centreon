import { always, cond, equals, isNil } from 'ramda';

import { Theme, useTheme } from '@mui/material';

import { getUnits, getYScale } from '../../common/timeSeries';
import { Line } from '../../common/timeSeries/models';

interface Props {
  displayedLines: Array<Line>;
  hideTooltip: () => void;
  leftScale: (value: number) => number;
  rightScale: (value: number) => number;
  showTooltip: (props) => void;
  thresholdLabels?: Array<string>;
  thresholdUnit?: string;
  thresholds?: Array<number>;
  width: number;
}

interface GetColorProps {
  index: number;
  theme: Theme;
}

const getColor = ({ index, theme }: GetColorProps): string => {
  return cond([
    [equals(0), always(theme.palette.warning.main)],
    [equals(1), always(theme.palette.error.main)]
  ])(index);
};

const Thresholds = ({
  thresholds,
  leftScale,
  rightScale,
  width,
  displayedLines,
  thresholdUnit,
  showTooltip,
  hideTooltip,
  thresholdLabels
}: Props): JSX.Element | null => {
  const theme = useTheme();

  if (!thresholds) {
    return null;
  }

  const [firstUnit, secondUnit, thirdUnit] = getUnits(
    displayedLines as Array<Line>
  );

  const shouldUseRightScale = equals(thresholdUnit, secondUnit);

  const yScale = shouldUseRightScale
    ? rightScale
    : getYScale({
        hasMoreThanTwoUnits: !isNil(thirdUnit),
        invert: null,
        leftScale,
        rightScale,
        secondUnit,
        unit: firstUnit
      });

  const thresholdScaledValues = thresholds
    .sort()
    .map((threshold) => yScale(threshold));

  return (
    <>
      {thresholdScaledValues.map((threshold, index) => {
        return (
          <line
            data-testid={`threshold-${threshold}`}
            key={`threshold-${thresholdLabels?.[index]}-${threshold}`}
            stroke={getColor({
              index,
              theme
            })}
            strokeDasharray="5,5"
            strokeWidth={2}
            x1={0}
            x2={width}
            y1={threshold}
            y2={threshold}
          />
        );
      })}
      {thresholdScaledValues.map((threshold, index) => {
        return (
          <line
            data-testid={`threshold-${threshold}-tooltip`}
            key={`threshold-${thresholdLabels?.[index]}-${threshold}-tooltip`}
            stroke="transparent"
            strokeWidth={4}
            x1={0}
            x2={width}
            y1={threshold}
            y2={threshold}
            onMouseEnter={(): void => {
              if (!thresholdLabels?.[index]) {
                return;
              }
              showTooltip({
                tooltipData: thresholdLabels?.[index],
                tooltipLeft: 0,
                tooltipTop: threshold
              });
            }}
            onMouseLeave={hideTooltip}
          />
        );
      })}
    </>
  );
};

export default Thresholds;
