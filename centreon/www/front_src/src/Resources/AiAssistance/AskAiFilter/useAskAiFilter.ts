import { Method, useMutationQuery } from '@centreon/ui';

import { aiResourceFilterEndpoint } from '../endpoints';
import { TranslateResourceFilterResult } from '../models';

interface AskAiFilterPayload {
  current_filter: string | null;
  prompt: string;
}

interface UseAskAiFilterState {
  isMutating: boolean;
  translate: (
    prompt: string,
    currentFilter: string
  ) => Promise<TranslateResourceFilterResult | null>;
}

const useAskAiFilter = (): UseAskAiFilterState => {
  const { mutateAsync, isMutating } = useMutationQuery<
    AskAiFilterPayload,
    undefined
  >({
    getEndpoint: () => aiResourceFilterEndpoint,
    method: Method.POST
  });

  const translate = (
    prompt: string,
    currentFilter: string
  ): Promise<TranslateResourceFilterResult | null> =>
    mutateAsync({
      payload: { current_filter: currentFilter || null, prompt }
    }).then((result) => {
      if ('isError' in result) {
        return null;
      }

      return result as unknown as TranslateResourceFilterResult;
    });

  return { isMutating, translate };
};

export default useAskAiFilter;
