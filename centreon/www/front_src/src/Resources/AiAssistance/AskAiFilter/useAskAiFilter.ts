import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  applyCurrentFilterDerivedAtom,
  searchAtom
} from '../../Filter/filterAtoms';
import { aiResourceFilterEndpoint } from '../endpoints';
import { TranslateResourceFilterResult } from '../models';
import { labelAiCouldNotTranslate } from '../translatedLabels';

interface UseAskAiFilterState {
  isMutating: boolean;
  translate: (prompt: string) => Promise<boolean>;
}

const useAskAiFilter = (): UseAskAiFilterState => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const setSearch = useSetAtom(searchAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);

  const { mutateAsync, isMutating } = useMutationQuery<
    { prompt: string },
    undefined
  >({
    getEndpoint: () => aiResourceFilterEndpoint,
    method: Method.POST
  });

  const translate = (prompt: string): Promise<boolean> =>
    mutateAsync({ payload: { prompt } }).then((result) => {
      if ('isError' in result) {
        return false;
      }

      const { search } = result as unknown as TranslateResourceFilterResult;

      if (!search) {
        showWarningMessage(t(labelAiCouldNotTranslate));

        return false;
      }

      setSearch(search);
      applyCurrentFilter();

      return true;
    });

  return { isMutating, translate };
};

export default useAskAiFilter;
