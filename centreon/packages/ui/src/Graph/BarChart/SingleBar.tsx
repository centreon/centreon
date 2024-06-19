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
  isHorizontal
}: UseSingleBarProps): JSX.Element => {
  const { barLength, barPadding } = useSingleBar({
    bar,
    isCenteredZero,
    isHorizontal,
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
    />
  );
};

const propsToMemoize = [
  'bar',
  'lines',
  'secondUnit',
  'size',
  'isCenteredZero',
  'isHorizontal'
];

export default memo(SingleBar, (prevProps, nextProps) =>
  equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps))
);
