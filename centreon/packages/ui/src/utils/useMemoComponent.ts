import * as React from 'react';

import { equals } from 'ramda';

export const useDeepCompare = (value: React.DependencyList): Array<number> => {
  const ref = React.useRef<React.DependencyList>();
  const signalRef = React.useRef<number>(0);

  if (!equals(value, ref.current)) {
    ref.current = value;
    signalRef.current += 1;
  }

  return [signalRef.current];
};

interface MemoComponent {
  Component: React.ReactElement;
  memoProps: Array<unknown>;
}

const useMemoComponent = ({
  Component,
  memoProps,
}: MemoComponent): JSX.Element =>
  React.useMemo(() => Component, useDeepCompare(memoProps));

export default useMemoComponent;
