import axios, { CancelToken } from 'axios';

const contentTypeHeaders = {
  'Content-Type': 'application/x-www-form-urlencoded',
};

interface GetDataParameters {
  endpoint: string;
  headers: Record<string, string>;
}

const getData =
  <TResult>(cancelToken: CancelToken) =>
  ({ endpoint, headers }: GetDataParameters): Promise<TResult> =>
    axios.get(endpoint, { cancelToken, headers }).then(({ data }) => data);

interface RequestWithData<TData> {
  data: TData;
  endpoint: string;
}

const patchData =
  <TData, TResult>(cancelToken: CancelToken) =>
  ({ endpoint, data }: RequestWithData<TData>): Promise<TResult> =>
    axios
      .patch(endpoint, data, {
        cancelToken,
        headers: contentTypeHeaders,
      })
      .then(({ data: result }) => result);

const postData =
  <TData, TResult>(cancelToken: CancelToken) =>
  ({ endpoint, data }: RequestWithData<TData>): Promise<TResult> =>
    axios
      .post(endpoint, data, {
        cancelToken,
        headers: contentTypeHeaders,
      })
      .then(({ data: result }) => result);

const putData =
  <TData, TResult>(cancelToken: CancelToken) =>
  ({ endpoint, data }: RequestWithData<TData>): Promise<TResult> =>
    axios
      .put(endpoint, data, {
        cancelToken,
        headers: contentTypeHeaders,
      })
      .then(({ data: result }) => result);

const deleteData =
  <TResult>(cancelToken: CancelToken) =>
  (endpoint: string): Promise<TResult> =>
    axios
      .delete(endpoint, {
        cancelToken,
        headers: contentTypeHeaders,
      })
      .then(({ data }) => data);

export { getData, patchData, postData, putData, deleteData };
