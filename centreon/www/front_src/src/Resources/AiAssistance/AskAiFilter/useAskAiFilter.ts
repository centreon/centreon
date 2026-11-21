import { translateResourceFilterDecoder } from '../api/decoders';
import { aiResourceFilterEndpoint } from '../api/endpoints';
import { useAiCall } from '../api/useAiCall';
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
  const { call, isMutating } = useAiCall<
    AskAiFilterPayload,
    TranslateResourceFilterResult
  >({
    decoder: translateResourceFilterDecoder,
    endpoint: aiResourceFilterEndpoint
  });

  const translate = (
    prompt: string,
    currentFilter: string
  ): Promise<TranslateResourceFilterResult | null> =>
    call({ current_filter: currentFilter || null, prompt });

  return { isMutating, translate };
};

export default useAskAiFilter;
