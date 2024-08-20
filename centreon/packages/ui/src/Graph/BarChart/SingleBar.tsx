import { memo } from 'react';

import { equals, pick } from 'ramda';

import { useSingleBar, UseSingleBarProps } from './useSingleBar';
import { BarStyle } from './models';

const SingleBar = ({
  lines,
  secondUnit,
  bar,
  leftScale,
  rightScale,
  size,
  isCenteredZero,
  isHorizontal,
  isTooltipHidden,
  barStyle
}: UseSingleBarProps & {
  barStyle: BarStyle;
}): JSX.Element => {
  const { barLength, barPadding, listeners } = useSingleBar({
    bar,
    isCenteredZero,
    isHorizontal,
    isTooltipHidden,
    leftScale,
    lines,
    rightScale,
    secondUnit,
    size
  });

  return (
    <rect
      data-testid={`single-bar-${bar.key}-${bar.index}-${bar.value}`}
      fill={bar.color}
      height={isHorizontal ? barLength : bar.height}
      opacity={barStyle.opacity}
      rx={(isHorizontal ? bar.width : bar.height) * barStyle.radius}
      width={isHorizontal ? bar.width : barLength}
      x={isHorizontal ? bar.x : barPadding}
      y={isHorizontal ? barPadding : bar.y}
      {...listeners}
    />
  );
};

const propsToMemoize = [
  'bar',
  'lines',
  'secondUnit',
  'size',
  'isCenteredZero',
  'isHorizontal',
  'isTooltipHidden',
  'barStyle'
];

export default memo(SingleBar, (prevProps, nextProps) =>
  equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps))
);
