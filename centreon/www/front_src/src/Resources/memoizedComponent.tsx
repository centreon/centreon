<<<<<<< HEAD
import { memo, NamedExoticComponent } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import { equals, pick } from 'ramda';

interface memoizeComponentParameters {
<<<<<<< HEAD
  Component: (props) => JSX.Element | null;
  memoProps: Array<string>;
}

const memoizeComponent = <T,>({
  memoProps,
  Component,
}: memoizeComponentParameters): NamedExoticComponent<T> =>
  memo(Component, (prevProps, nextProps) =>
=======
  Component: (props) => JSX.Element;
  memoProps: Array<string>;
}

const memoizeComponent = <T extends unknown>({
  memoProps,
  Component,
}: memoizeComponentParameters): React.NamedExoticComponent<T> =>
  React.memo(Component, (prevProps, nextProps) =>
>>>>>>> centreon/dev-21.10.x
    equals(pick(memoProps, prevProps), pick(memoProps, nextProps)),
  );

export default memoizeComponent;
