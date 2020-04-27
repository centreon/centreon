import axios from 'axios';

const getData = <TData>(cancelToken) => (endpoint): Promise<TData> =>
  axios.get(endpoint, { cancelToken }).then(({ data }) => data);

export { getData };
