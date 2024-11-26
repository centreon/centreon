import { useAtom, atom, useSetAtom } from 'jotai';
import { isNil } from 'ramda';

import { useRequest, getData } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
import type { User } from '@centreon/ui-context';

import { userDecoder } from '../api/decoders';
import { userEndpoint } from '../api/endpoint';

export const areUserParametersLoadedAtom = atom<boolean | null>(null);

const useUser = (): (() => null | Promise<void>) => {
  const { sendRequest: getUser } = useRequest<User>({
    decoder: userDecoder,
    httpCodesBypassErrorSnackbar: [403, 401],
    request: getData
  });

  const [areUserParametersLoaded, setAreUserParametersLoaded] = useAtom(
    areUserParametersLoadedAtom
  );
  const setUser = useSetAtom(userAtom);

  const loadUser = (): null | Promise<void> => {
    if (areUserParametersLoaded) {
      return null;
    }

    return getUser({
      endpoint: userEndpoint
    })
      .then((retrievedUser) => {
        if (isNil(retrievedUser)) {
          return;
        }

        const {
          id,
          alias,
          isExportButtonEnabled,
          locale,
          name,
          themeMode,
          timezone,
          use_deprecated_pages: useDeprecatedPages,
          default_page: defaultPage,
          user_interface_density,
          dashboard,
          isAdmin,
          canManageApiTokens
        } = retrievedUser as User;

        setUser({
          alias,
          canManageApiTokens,
          dashboard,
          default_page: defaultPage || '/monitoring/resources',
          id,
          isAdmin,
          isExportButtonEnabled,
          locale: locale || 'en',
          name,
          themeMode,
          timezone,
          use_deprecated_pages: useDeprecatedPages,
          user_interface_density
        });
        setAreUserParametersLoaded(true);
      })
      .catch(() => {
        setAreUserParametersLoaded(false);
      });
  };

  return loadUser;
};

export default useUser;
