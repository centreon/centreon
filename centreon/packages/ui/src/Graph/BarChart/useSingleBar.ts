import { useMemo } from 'react';

import { equals, gt } from 'ramda';

import { Line } from '../common/timeSeries/models';
import { useDeepMemo } from '../..';

const getInvertedBarLength = ({
  useRightScale,
  rightScale,
  leftScale,
  value
}): number | null => {
  const scale = useRightScale ? rightScale : leftScale;

  return scale(value);
};

const getBarLength = ({
  size,
  value,
  invertedBarLength,
  lengthToMatchZero,
  isCenteredZero,
  isHorizontal
}): number => {
  if (!value) {
    return 0;
  }

  if (!isHorizontal && gt(0, value) && isCenteredZero) {
    return size - lengthToMatchZero - invertedBarLength;
  }

  if (!isHorizontal && gt(0, value) && gt(invertedBarLength, 0)) {
    return invertedBarLength;
  }

  if (!isHorizontal && gt(0, value)) {
    return invertedBarLength + (size - lengthToMatchZero);
  }

  if (!isHorizontal) {
    return invertedBarLength - (size - lengthToMatchZero);
  }

  if (value < 0) {
    return Math.abs(invertedBarLength) - (size - lengthToMatchZero);
  }

  if (isCenteredZero) {
    const barLength = size - invertedBarLength;

    return size - invertedBarLength - barLength / 2;
  }

  return size - invertedBarLength - lengthToMatchZero;
};

export interface UseSingleBarProps {
  bar: {
    color: string;
    height: number;
    index: number;
    key: string;
    value: number | null;
    width: number;
    x: number;
    y: number;
  };
  isCenteredZero?: boolean;
  isHorizontal: boolean;
  leftScale;
  lines: Array<Line>;
  rightScale;
  secondUnit?: string;
  size: number;
}

interface UseSingleBarState {
  barLength: number;
  barPadding: number;
}

export const useSingleBar = ({
  lines,
  secondUnit,
  bar,
  leftScale,
  rightScale,
  size,
  isCenteredZero,
  isHorizontal
}: UseSingleBarProps): UseSingleBarState => {
  const metric = useDeepMemo({
    deps: [lines, bar.key],
    variable: lines.find(({ metric_id }) => equals(metric_id, Number(bar.key)))
  });

  const useRightScale = useMemo(
    () => equals(secondUnit, metric?.unit),
    [secondUnit, metric?.unit]
  );

  const left0Height = useMemo(() => leftScale(0), [leftScale(0)]);
  const right0Height = useMemo(() => rightScale(0), [rightScale(0)]);

  const invertedBarLength = useMemo(
    () =>
      getInvertedBarLength({
        leftScale,
        rightScale,
        useRightScale,
        value: bar.value
      }),
    [bar.value, useRightScale, leftScale, rightScale]
  );

  const lengthToMatchZero = useMemo(
    () => size - (useRightScale ? right0Height : left0Height),
    [useRightScale, rightScale, leftScale]
  );

  const barLength = useMemo(
    () =>
      getBarLength({
        invertedBarLength,
        isCenteredZero,
        isHorizontal,
        lengthToMatchZero,
        size,
        value: bar.value
      }),
    [
      size,
      invertedBarLength,
      isCenteredZero,
      isHorizontal,
      lengthToMatchZero,
      bar.value
    ]
  );

  const barPadding = useMemo(
    () =>
      isHorizontal
        ? size -
          barLength -
          lengthToMatchZero +
          ((bar.value ?? 0) < 0 ? barLength : 0)
        : size - lengthToMatchZero - ((bar.value ?? 0) < 0 ? barLength : 0),
    [isHorizontal, barLength, bar.value, barLength]
  );

  return {
    barLength,
    barPadding
  };
};
