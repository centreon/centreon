import { equals, pick } from 'ramda';
import { memo, NamedExoticComponent } from 'react';

interface MemoizeComponentParameters {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  Component: (props: any) => JSX.Element | null;
  memoProps: Array<string>;
}

const memoizeComponent = <T,>({
  memoProps,
  Component
}: MemoizeComponentParameters): NamedExoticComponent<T> =>
  memo(Component, (prevProps, nextProps) =>
    equals(pick(memoProps, prevProps), pick(memoProps, nextProps))
  );

export default memoizeComponent;
