import {
  RenderResult,
  render,
  screen,
  waitFor
} from '@centreon/ui/test/testRenderer';
import { isAdditiveInheritanceEnabledAtom } from '@centreon/ui-context';

import axios from 'axios';

import {
  aclEndpoint,
  externalTranslationEndpoint,
  internalTranslationEndpoint,
  parametersEndpoint
} from '../App/endpoint';
import {
  platformInstallationStatusEndpoint,
  userEndpoint
} from '../api/endpoint';
import { labelAuthenticationDenied } from '../FallbackPages/AuthenticationDenied/translatedLabels';
import { retrievedFederatedModule } from '../federatedModules/mocks';
import { labelConnect } from '../Login/translatedLabels';
import { retrievedNavigation } from '../Navigation/mocks';
import { navigationEndpoint } from '../Navigation/useNavigation';
import { platformInstallationStatusAtom } from './atoms/platformInstallationStatusAtom';
import Provider, { store } from './Provider';
import {
  retrievedActionsAcl,
  retrievedLoginConfiguration,
  retrievedParameters,
  retrievedProvidersConfiguration,
  retrievedTranslations,
  retrievedUser,
  retrievedWeb
} from './testUtils';
import { labelCentreonIsLoading } from './translatedLabels';
import { areUserParametersLoadedAtom } from './useUser';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const cancelTokenRequestParam = { cancelToken: {} };

jest.mock('../Navigation/Sidebar/Logo/centreon.png');

jest.mock('../Header', () => {
  const Header = (): JSX.Element => {
    return <div />;
  };

  return {
    __esModule: true,
    default: Header
  };
});

jest.mock('../components/mainRouter', () => {
  const MainRouter = (): JSX.Element => {
    return <div />;
  };

  return {
    __esModule: true,
    default: MainRouter
  };
});

const renderMain = (): RenderResult => render(<Provider />);

const mockDefaultGetRequests = (): void => {
  mockedAxios.get
    .mockResolvedValueOnce({
      data: {
        has_upgrade_available: false,
        is_installed: true
      }
    })
    .mockResolvedValueOnce({
      data: retrievedUser
    })
    .mockResolvedValueOnce({
      data: {
        feature_flags: {},
        is_cloud_platform: false
      }
    })
    .mockResolvedValueOnce({
      data: retrievedWeb
    })
    .mockResolvedValueOnce({
      data: retrievedTranslations
    })
    .mockResolvedValueOnce({
      data: retrievedNavigation
    })
    .mockResolvedValueOnce({
      data: retrievedFederatedModule
    })
    .mockResolvedValueOnce({
      data: retrievedParameters
    })
    .mockResolvedValueOnce({
      data: retrievedActionsAcl
    })
    .mockResolvedValueOnce({
      data: retrievedLoginConfiguration
    })
    .mockResolvedValueOnce({
      data: null
    });
};

// Answered by URL rather than in call order, so a request added or moved in
// the start-up sequence cannot hand one endpoint another one's response.
const mockGetRequestsByUrl = (parameters: object): void => {
  const responses: Array<[string, unknown]> = [
    [
      platformInstallationStatusEndpoint,
      { has_upgrade_available: false, is_installed: true }
    ],
    [userEndpoint, retrievedUser],
    ['platform/features', { feature_flags: {}, is_cloud_platform: false }],
    ['platform/versions', retrievedWeb],
    ['allTranslations', retrievedTranslations],
    [navigationEndpoint, retrievedNavigation],
    [parametersEndpoint, parameters],
    [aclEndpoint, retrievedActionsAcl]
  ];

  mockedAxios.get.mockImplementation((url: string) =>
    Promise.resolve({
      data: responses.find(([endpoint]) => url.includes(endpoint))?.[1] ?? null
    })
  );
};

const mockRedirectFromLoginPageGetRequests = (): void => {
  mockedAxios.get
    .mockResolvedValueOnce({
      data: {
        has_upgrade_available: false,
        is_installed: true
      }
    })
    .mockResolvedValueOnce({
      data: retrievedUser
    })
    .mockResolvedValueOnce({
      data: {
        feature_flags: {},
        is_cloud_platform: false
      }
    })
    .mockResolvedValueOnce({
      data: retrievedWeb
    })
    .mockResolvedValueOnce({
      data: retrievedTranslations
    })
    .mockResolvedValueOnce({
      data: retrievedProvidersConfiguration
    })
    .mockResolvedValueOnce({
      data: retrievedTranslations
    })
    .mockResolvedValueOnce({
      data: retrievedNavigation
    })
    .mockResolvedValueOnce({
      data: retrievedParameters
    })
    .mockResolvedValueOnce({
      data: retrievedActionsAcl
    })
    .mockResolvedValueOnce({
      data: retrievedLoginConfiguration
    })
    .mockResolvedValue({
      data: null
    });
};

const mockNotConnectedGetRequests = (): void => {
  mockedAxios.get
    .mockResolvedValueOnce({
      data: {
        has_upgrade_available: false,
        is_installed: true
      }
    })
    .mockRejectedValueOnce({
      response: { status: 403 }
    })
    .mockResolvedValueOnce({
      data: {
        feature_flags: {},
        is_cloud_platform: false
      }
    })
    .mockResolvedValueOnce({
      data: retrievedWeb
    })
    .mockResolvedValueOnce({
      data: retrievedTranslations
    })
    .mockResolvedValueOnce({
      data: retrievedProvidersConfiguration
    })
    .mockResolvedValueOnce({
      data: retrievedLoginConfiguration
    });
};

const mockInstallGetRequests = (): void => {
  mockedAxios.get.mockResolvedValueOnce({
    data: {
      has_upgrade_available: false,
      is_installed: false
    }
  });
};

const mockUpgradeAndUserDisconnectedGetRequests = (): void => {
  mockedAxios.get
    .mockResolvedValueOnce({
      data: {
        has_upgrade_available: true,
        is_installed: true
      }
    })
    .mockRejectedValueOnce({
      response: { status: 403 }
    })
    .mockResolvedValueOnce({
      data: retrievedWeb
    });
};

const mockUpgradeAndUserConnectedGetRequests = (): void => {
  mockedAxios.get
    .mockResolvedValueOnce({
      data: {
        has_upgrade_available: true,
        is_installed: true
      }
    })
    .mockResolvedValueOnce({
      data: retrievedUser
    })
    .mockResolvedValueOnce({
      data: retrievedWeb
    })
    .mockResolvedValueOnce({
      data: retrievedTranslations
    })
    .mockResolvedValueOnce({
      data: retrievedNavigation
    })
    .mockResolvedValueOnce({
      data: retrievedParameters
    })
    .mockResolvedValueOnce({
      data: retrievedActionsAcl
    })
    .mockResolvedValueOnce({
      data: retrievedLoginConfiguration
    })
    .mockResolvedValueOnce({
      data: null
    });
};

describe('Main', () => {
  beforeEach(() => {
    mockedAxios.get.mockReset();
    window.history.pushState({}, '', '/');
    // Provider.tsx's jotai store is a module-level singleton shared across
    // every render() in this file, so state set by one test (the user is
    // loaded, the install status is known...) otherwise leaks into the next.
    store.set(areUserParametersLoadedAtom, null);
    store.set(platformInstallationStatusAtom, null);
    store.set(isAdditiveInheritanceEnabledAtom, false);
  });

  it('displays the login page when the path is "/login" and the user is not connected', async () => {
    window.history.pushState({}, '', '/login');
    mockNotConnectedGetRequests();

    renderMain();

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        externalTranslationEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        userEndpoint,
        cancelTokenRequestParam
      );
    });

    // biome-ignore lint: test purpose
    expect(window.location.href).toBe('http://localhost/login');

    await waitFor(() => {
      expect(screen.getByLabelText(labelConnect)).toBeInTheDocument();
    });
  });

  it('displays the authentication denied page', async () => {
    window.history.pushState({}, '', '/authentication-denied');

    mockDefaultGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelAuthenticationDenied)).toBeInTheDocument();
    });
  });

  it('redirects the user to the install page when the retrieved web versions does not contain an installed version', async () => {
    window.history.pushState({}, '', '/');
    mockInstallGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(decodeURI(window.location.href)).toBe(
        // biome-ignore lint: test purpose
        'http://localhost/install/install.php'
      );
    });
  });

  it('redirects the user to the upgrade page when the retrieved web versions contains an available version and the user is disconnected', async () => {
    window.history.pushState({}, '', '/');
    mockUpgradeAndUserDisconnectedGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(decodeURI(window.location.href)).toBe(
        // biome-ignore lint: test purpose
        'http://localhost/install/upgrade.php'
      );
    });
  });

  // While an upgrade is pending, the database may miss the tables the
  // platform APIs read (useMain.ts skips loadUser(), versions and features),
  // so even a connected user must be sent to the upgrade page first.
  it('redirects the user to the upgrade page without loading the user when the retrieved web versions contains an available version and the user is connected', async () => {
    window.history.pushState({}, '', '/');
    mockUpgradeAndUserConnectedGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(decodeURI(window.location.href)).toBe(
        // biome-ignore lint: test purpose
        'http://localhost/install/upgrade.php'
      );
    });

    expect(mockedAxios.get).not.toHaveBeenCalledWith(
      userEndpoint,
      expect.anything()
    );
  });

  it('gets the translations, navigation data and the parameters related to the account when the user is already connected', async () => {
    window.history.pushState({}, '', '/');
    mockDefaultGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        userEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        navigationEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        parametersEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        aclEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        internalTranslationEndpoint,
        cancelTokenRequestParam
      );
    });
  });

  it('turns additive inheritance on when the platform parameters say so', async () => {
    mockGetRequestsByUrl({
      ...retrievedParameters,
      is_additive_inheritance_enabled: true
    });

    renderMain();

    await waitFor(() => {
      expect(store.get(isAdditiveInheritanceEnabledAtom)).toBe(true);
    });
  });

  it('redirects the user to his default page when the current location is the login page and the user is connected', async () => {
    window.history.pushState({}, '', '/login');
    mockRedirectFromLoginPageGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        aclEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(window.location.href).toBe(
        // biome-ignore lint: test purpose
        'http://localhost/monitoring/resources'
      );
    });
  });

  it('displays a message when the authentication from an external provider fails ', async () => {
    window.history.pushState(
      {},
      '',
      '/?authenticationError=Authentication%20failed'
    );
    mockDefaultGetRequests();

    renderMain();

    await waitFor(() => {
      expect(screen.getByText('Authentication failed')).toBeInTheDocument();
    });
  });
});
