import { equals } from 'ramda';
import { type DependencyList, type ReactElement, useMemo, useRef } from 'react';

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
  useMemo(() => Component, [...useDeepCompare(memoProps), Component]);

export default useMemoComponent;
