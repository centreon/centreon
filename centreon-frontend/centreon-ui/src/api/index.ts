import axios from 'axios';

const getData = <TData>({ endpoint, requestParams }): Promise<TData> =>
  axios.get(endpoint, requestParams).then(({ data }) => data);

export { getData };
