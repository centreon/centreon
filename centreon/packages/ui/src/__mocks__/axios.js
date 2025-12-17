const mockAxios = jest.genMockFromModule('axios');

mockAxios.create = jest.fn(() => mockAxios);

mockAxios.CancelToken = {
  source: () => ({
    cancel: jest.fn(),
    token: {}
  })
};

export default mockAxios;
