import { useCallback } from 'react';

import { useDeepCompare } from './useMemoComponent';

interface UseDeepCallback<TParameters, TReturn, TMemoProps> {
  callback: (props: TParameters) => TReturn;
  deps: Array<TMemoProps>;
}

const useDeepCallback = <TParameters, TReturn, TMemoProps>({
  deps,
  callback
}: UseDeepCallback<TParameters, TReturn, TMemoProps>): ((
  props: TParameters
) => TReturn) => useCallback((props) => callback(props), useDeepCompare(deps));

export default useDeepCallback;
