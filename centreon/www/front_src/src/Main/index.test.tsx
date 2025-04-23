import axios from 'axios';

import {
  RenderResult,
  render,
  screen,
  waitFor
} from '@centreon/ui/test/testRenderer';

import {
  aclEndpoint,
  externalTranslationEndpoint,
  internalTranslationEndpoint,
  parametersEndpoint
} from '../App/endpoint';
import { labelAuthenticationDenied } from '../FallbackPages/AuthenticationDenied/translatedLabels';
import { labelConnect } from '../Login/translatedLabels';
import { retrievedNavigation } from '../Navigation/mocks';
import { navigationEndpoint } from '../Navigation/useNavigation';
import {
  platformInstallationStatusEndpoint,
  userEndpoint
} from '../api/endpoint';
import { retrievedFederatedModule } from '../federatedModules/mocks';

import Provider from './Provider';
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
  });

  // biome-ignore lint/suspicious/noFocusedTests: To migrate to Cypress
  it.only('displays the login page when the path is "/login" and the user is not connected', async () => {
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

    expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith(
        platformInstallationStatusEndpoint,
        cancelTokenRequestParam
      );
    });

    await waitFor(() => {
      expect(decodeURI(window.location.href)).toBe(
        'http://localhost/install/install.php'
      );
    });
  });

  it('redirects the user to the upgrade page when the retrieved web versions contains an available version and the user is disconnected', async () => {
    window.history.pushState({}, '', '/');
    mockUpgradeAndUserDisconnectedGetRequests();

    renderMain();

    expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();

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
      expect(decodeURI(window.location.href)).toBe(
        'http://localhost/install/upgrade.php'
      );
    });
  });

  it('does not redirect the user to the upgrade page when the retrieved web versions contains an available version and the user is connected', async () => {
    window.history.pushState({}, '', '/');
    mockUpgradeAndUserConnectedGetRequests();

    renderMain();

    expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();

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
      expect(decodeURI(window.location.href)).toBe(
        'http://localhost/monitoring/resources'
      );
    });
  });

  it('gets the translations, navigation data and the parameters related to the account when the user is already connected', async () => {
    window.history.pushState({}, '', '/');
    mockDefaultGetRequests();

    renderMain();

    expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();

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

    expect(mockedAxios.get).toHaveBeenCalledWith(
      parametersEndpoint,
      cancelTokenRequestParam
    );

    expect(mockedAxios.get).toHaveBeenCalledWith(
      aclEndpoint,
      cancelTokenRequestParam
    );

    expect(mockedAxios.get).toHaveBeenCalledWith(
      internalTranslationEndpoint,
      cancelTokenRequestParam
    );
  });

  it('redirects the user to his default page when the current location is the login page and the user is connected', async () => {
    window.history.pushState({}, '', '/login');
    mockRedirectFromLoginPageGetRequests();

    renderMain();

    expect(screen.getByText(labelCentreonIsLoading)).toBeInTheDocument();

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
        'http://localhost/monitoring/resources'
      );
    });
  });

  it('displays a message when the authentication from an external provider fails ', () => {
    window.history.pushState(
      {},
      '',
      '/?authenticationError=Authentication%20failed'
    );
    mockDefaultGetRequests();

    renderMain();

    expect(screen.getByText('Authentication failed')).toBeInTheDocument();
  });
});
