import { waitFor } from '@testing-library/dom';
import { act, renderHook } from '@testing-library/react-hooks';
import axios from 'axios';

import usePlatformVersions from '../Main/usePlatformVersions';

import { retrievedFederatedModule } from './mocks';
import useFederatedModules from './useFederatedModules';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const retrievedWebVersions = {
  modules: {
    'centreon-bam-server': {
      fix: '0',
      major: '1',
      minor: '0',
      version: '1.0.0'
    }
  },
  web: {
    fix: '0',
    major: '23',
    minor: '04',
    version: '23.04.1'
  },
  widgets: {}
};

describe('external components', () => {
  beforeEach(() => {
    mockedAxios.get.mockReset();
    mockedAxios.get
      .mockResolvedValueOnce({ data: retrievedWebVersions })
      .mockResolvedValue({ data: retrievedFederatedModule });
  });

  it('populates the federated components atom with the data retrieved from the API', async () => {
    const { result } = renderHook(() => ({
      ...useFederatedModules(),
      ...usePlatformVersions()
    }));

    expect(result.current.federatedModules).toEqual(null);

    act(() => {
      result.current.getPlatformVersions();
    });

    await waitFor(() => {
      expect(result.current.getModules()).toEqual(['centreon-bam-server']);
    });

    act(() => {
      result.current.getFederatedModulesConfigurations();
    });

    await waitFor(() => {
      expect(result.current.federatedModules).toEqual([
        retrievedFederatedModule
      ]);
    });
  });
});
