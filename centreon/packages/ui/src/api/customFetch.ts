import { equals } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

interface ApiErrorResponse {
  code: number;
  message: string;
}

export interface ResponseError {
  additionalInformation?;
  isError: boolean;
  message: string;
  statusCode: number;
}

export interface CatchErrorProps {
  data?: ApiErrorResponse;
  statusCode: number;
}

interface CustomFetchProps<T> {
  catchError?: (props: CatchErrorProps) => void;
  decoder?: JsonDecoder.Decoder<T>;
  defaultFailureMessage?: string;
  endpoint: string;
  headers?: Headers;
  isMutation?: boolean;
  method?: string;
  payload?;
  signal?: AbortSignal;
}

export const customFetch = <T>({
  endpoint,
  catchError = (): undefined => undefined,
  signal,
  headers,
  decoder,
  defaultFailureMessage = 'Something went wrong',
  isMutation = false,
  payload,
  method = 'GET'
}: CustomFetchProps<T>): Promise<T | ResponseError> => {
  const defaultOptions = { headers, method, signal };

  const options = isMutation
    ? {
        ...defaultOptions,
        body: payload instanceof FormData ? payload : JSON.stringify(payload)
      }
    : defaultOptions;

  return fetch(endpoint, options)
    .then((response) => {
      if (equals(response.status, 204)) {
        return {
          isError: false,
          message: ''
        };
      }

      return response.json().then((data) => {
        if (!response.ok) {
          const defaultError = {
            code: -1,
            message: data.message || defaultFailureMessage
          };
          catchError({
            data: data || defaultError,
            statusCode: response.status
          });

          return {
            additionalInformation: data,
            isError: true,
            message: data.message || defaultFailureMessage,
            statusCode: response.status
          };
        }

        if (decoder) {
          return decoder.decodeToPromise(data);
        }

        return data;
      });
    })
    .catch((error: Error) => {
      const defaultError = {
        code: -1,
        message: error.message || defaultFailureMessage
      };

      catchError({
        data: defaultError,
        statusCode: 0
      });

      return {
        isError: true,
        message: error.message || defaultFailureMessage,
        statusCode: 0
      };
    });
};
