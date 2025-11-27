import { identity } from 'ramda';
import { useMemo } from 'react';
import { margin } from '../Chart/common';

export const useMarginTop = ({
  title,
  units
}: { title?: string; units: Array<string> }): number => {
  const hasUnits = useMemo(() => units.some(identity), [units]);

  const marginTop = useMemo(
    () => (title || hasUnits ? margin.top : 4),
    [title, hasUnits]
  );

  return marginTop;
};
