import axios, { CancelToken } from 'axios';

const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };

const getData = <TData>(cancelToken: CancelToken) => (
  endpoint: string,
): Promise<TData> =>
  axios.get(endpoint, { cancelToken }).then(({ data }) => data);

interface RequestWithData<TData> {
  endpoint: string;
  data: TData;
}

const postData = <TData>(cancelToken: CancelToken) => ({
  endpoint,
  data,
}: RequestWithData<TData>): Promise<TData> =>
  axios
    .post(endpoint, data, {
      headers,
      cancelToken,
    })
    .then(({ data: result }) => result);

const putData = <TData>(cancelToken: CancelToken) => ({
  endpoint,
  data,
}: RequestWithData<TData>): Promise<TData> =>
  axios
    .put(endpoint, data, {
      headers,
      cancelToken,
    })
    .then(({ data: result }) => result);

const deleteData = <TData>(cancelToken: CancelToken) => (
  endpoint: string,
): Promise<TData> =>
  axios
    .delete(endpoint, {
      headers,
      cancelToken,
    })
    .then(({ data }) => data);

export { getData, postData, putData, deleteData };
