import { useAtomValue } from 'jotai';
import { path } from 'ramda';

import { useFetchQuery } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';
import centreonLogo from '../assets/logo-centreon-colors.svg';

import { loginPageCustomisationDecoder } from './api/decoder';
import { loginPageCustomisationEndpoint } from './api/endpoint';
import { LoginPageCustomisation } from './models';
import useWallpaper from './useWallpaper';

interface UseGetLoginCustomDataState {
  loginPageCustomisation: LoginPageCustomisation;
}

const defaultLoginPageCustomisation: LoginPageCustomisation = {
  customText: null,
  iconSource: centreonLogo,
  imageSource: null,
  platformName: null,
  textPosition: null
};

const useGetLoginCustomData = (): UseGetLoginCustomDataState => {
  const wallpaper = useWallpaper();

  const platformVersions = useAtomValue(platformVersionsAtom);
  const { data: loginPageCustomisationData, isFetching } =
    useFetchQuery<LoginPageCustomisation>({
      decoder: loginPageCustomisationDecoder,
      getEndpoint: () => loginPageCustomisationEndpoint,
      getQueryKey: () => ['loginPageCustomisation'],
      httpCodesBypassErrorSnackbar: [404, 401],
      queryOptions: {
        enabled: !!path(
          ['modules', 'centreon-it-edition-extensions'],
          platformVersions
        ),
        retry: false,
        suspense: false
      }
    });

  const loginPageCustomisation = isFetching
    ? defaultLoginPageCustomisation
    : {
        customText:
          loginPageCustomisationData?.customText ||
          defaultLoginPageCustomisation.customText,
        iconSource:
          loginPageCustomisationData?.iconSource ||
          defaultLoginPageCustomisation.iconSource,
        imageSource: loginPageCustomisationData?.imageSource || wallpaper,
        platformName:
          loginPageCustomisationData?.platformName ||
          defaultLoginPageCustomisation.platformName,
        textPosition:
          loginPageCustomisationData?.textPosition ||
          defaultLoginPageCustomisation.textPosition
      };

  return {
    loginPageCustomisation
  };
};

export default useGetLoginCustomData;
