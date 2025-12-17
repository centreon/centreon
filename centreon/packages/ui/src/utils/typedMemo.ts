import { memo } from 'react';

/**
 * Memoize a component with a deep comparison of props like classic memo function
 * Add typescript props type inference.
 * @param Component The component to memoize.
 * @param propsAreEqual An optional function that compares the previous and next props.
 * @returns The memoized component.
 * @example
 * const MemoizedComponent = typedMemo(Component, (prevProps, nextProps) => {
 *  return prevProps.prop === nextProps.prop;
 * });
 */
export const typedMemo: <T>(
  component: T,
  propsAreEqual?: (prevProps: Readonly<T>, nextProps: Readonly<T>) => boolean
) => T = memo;
