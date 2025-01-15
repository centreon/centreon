import { DependencyList, ReactElement, useMemo, useRef } from 'react';

import { equals } from 'ramda';

export const useDeepCompare = (value: DependencyList): Array<number> => {
  const ref = useRef<React.DependencyList>();
  const signalRef = useRef<number>(0);

  if (!equals(value, ref.current)) {
    ref.current = value;
    signalRef.current += 1;
  }

  return [signalRef.current];
};

interface MemoComponent {
  Component: ReactElement;
  memoProps: Array<unknown>;
}

export const useMemoComponent = ({
  Component,
  memoProps
}: MemoComponent): JSX.Element =>
  useMemo(() => Component, useDeepCompare(memoProps));

export default useMemoComponent;
