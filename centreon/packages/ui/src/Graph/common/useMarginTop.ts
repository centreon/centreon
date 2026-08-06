import { useMemo } from 'react';

import { margin } from '../Chart/common';

export const useMarginTop = ({
  title
}: {
  title?: string;
  units: Array<string>;
}): number => {
  const marginTop = useMemo(() => (title ? margin.top : 4), [title]);

  return marginTop;
};
