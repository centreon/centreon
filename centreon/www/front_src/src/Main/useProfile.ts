import { atom, useSetAtom } from 'jotai';

import { profileAtom } from '@centreon/ui-context';
import type { Profile } from '@centreon/ui-context';

import { useFetchQuery } from '@centreon/ui';
import { has } from 'ramda';
import { profileDecoder } from '../api/decoders';
import { profileEndpoint } from '../api/endpoint';

export const areUserParametersLoadedAtom = atom<boolean | null>(null);

const useProfile = (): (() => void) => {
  const setProfile = useSetAtom(profileAtom);

  const { fetchQuery } = useFetchQuery<Profile>({
    decoder: profileDecoder,
    getEndpoint: () => profileEndpoint,
    getQueryKey: () => ['loadProfile'],
    queryOptions: {
      suspense: false,
      enabled: false
    }
  });

  const loadProfile = (): void => {
    fetchQuery().then((response) => {
      const isError = has('isError', response);

      if (isError) {
        return;
      }

      setProfile(response);
    });
  };

  return loadProfile;
};

export default useProfile;
