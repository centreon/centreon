import axios, { CancelToken } from 'axios';

const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };

const getData = <TResult>(cancelToken: CancelToken) => (
  endpoint: string,
): Promise<TResult> =>
  axios.get(endpoint, { cancelToken }).then(({ data }) => data);

interface RequestWithData<TData> {
  data: TData;
  endpoint: string;
}

const patchData = <TData, TResult>(cancelToken: CancelToken) => ({
  endpoint,
  data,
}: RequestWithData<TData>): Promise<TResult> =>
  axios
    .patch(endpoint, data, {
      cancelToken,
      headers,
    })
    .then(({ data: result }) => result);

const postData = <TData, TResult>(cancelToken: CancelToken) => ({
  endpoint,
  data,
}: RequestWithData<TData>): Promise<TResult> =>
  axios
    .post(endpoint, data, {
      cancelToken,
      headers,
    })
    .then(({ data: result }) => result);

const putData = <TData, TResult>(cancelToken: CancelToken) => ({
  endpoint,
  data,
}: RequestWithData<TData>): Promise<TResult> =>
  axios
    .put(endpoint, data, {
      cancelToken,
      headers,
    })
    .then(({ data: result }) => result);

const deleteData = <TResult>(cancelToken: CancelToken) => (
  endpoint: string,
): Promise<TResult> =>
  axios
    .delete(endpoint, {
      cancelToken,
      headers,
    })
    .then(({ data }) => data);

export { getData, patchData, postData, putData, deleteData };
