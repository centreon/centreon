import { NamedExoticComponent, memo } from 'react';

import { equals, pick } from 'ramda';

interface MemoizeComponentParameters {
  Component: (props) => JSX.Element | null;
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
