import { memo } from 'react';

import { equals, pick } from 'ramda';

import { useSingleBar, UseSingleBarProps } from './useSingleBar';

const SingleBar = ({
  lines,
  secondUnit,
  bar,
  leftScale,
  rightScale,
  size,
  isCenteredZero,
  isHorizontal,
  isTooltipHidden
}: UseSingleBarProps): JSX.Element => {
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
      fill={bar.color}
      height={isHorizontal ? barLength : bar.height}
      rx={(isHorizontal ? bar.width : bar.height) * 0.2}
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
  'isTooltipHidden'
];

export default memo(SingleBar, (prevProps, nextProps) =>
  equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps))
);
