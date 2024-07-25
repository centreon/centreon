import { equals, isNil, startsWith } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import { Method } from './useMutationQuery';

interface ApiErrorResponse {
  code: number;
  message: string;
}

export interface ResponseError {
  additionalInformation?;
  data?;
  isError: boolean;
  message: string;
  statusCode: number;
}

export interface CatchErrorProps {
  data?: ApiErrorResponse;
  statusCode: number;
}

interface CustomFetchProps<T> {
  baseEndpoint?: string;
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
  method = 'GET',
  baseEndpoint = './api/latest'
}: CustomFetchProps<T>): Promise<T | ResponseError> => {
  const defaultOptions = { headers, method, signal };

  const formattedEndpoint =
    !isNil(baseEndpoint) &&
    !startsWith(baseEndpoint, endpoint) &&
    !startsWith('./api/internal.php', endpoint)
      ? `${baseEndpoint}${endpoint}`
      : endpoint;

  const isFormData = payload instanceof FormData;

  const options = isMutation
    ? {
        ...defaultOptions,
        body: isFormData ? payload : JSON.stringify(payload),
        headers: isFormData ? undefined : headers
      }
    : defaultOptions;

  return fetch(formattedEndpoint, options)
    .then((response) => {
      if (equals(response.status, 204)) {
        return {
          isError: false,
          message: ''
        };
      }

      return response
        .json()
        .then((data) => {
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

          if (equals(response.status, 207)) {
            return {
              data: data.results,
              isError: false,
              message: '',
              statusCode: response.status
            };
          }

          if (decoder) {
            return decoder.decodeToPromise(data).catch((error: string) => {
              catchError({
                data: {
                  code: -1,
                  message: error || defaultFailureMessage
                },
                statusCode: response.status
              });

              return {
                isError: true,
                message: error || defaultFailureMessage,
                statusCode: response.status
              };
            });
          }

          return data;
        })
        .catch(() => {
          if (equals(method, Method.GET)) {
            return {
              isError: true,
              message: defaultFailureMessage,
              statusCode: 0
            };
          }

          return null;
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
