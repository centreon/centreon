import * as React from 'react';

import { useDeepCompare } from './useMemoComponent';

interface UseDeepMemo<TVariable, TMemoProps> {
  deps: Array<TMemoProps>;
  variable: TVariable;
}

const useDeepMemo = <TVariable, TMemoProps>({
  deps,
  variable,
}: UseDeepMemo<TVariable, TMemoProps>): TVariable =>
  React.useMemo(() => variable, useDeepCompare(deps));

export default useDeepMemo;
