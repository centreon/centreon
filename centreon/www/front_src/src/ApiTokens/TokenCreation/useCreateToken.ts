import dayjs from 'dayjs';
import { equals } from 'ramda';

import {
  Method,
  ResponseError,
  useLocaleDateTimeFormat,
  useMutationQuery
} from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';
import { createdTokenDecoder } from '../api/decoder';
import { createTokenEndpoint } from '../api/endpoints';

import { CreatedToken, dataDuration } from './models';

interface UseCreateToken {
  createToken: (params: Required<CreateTokenFormValues>) => void;
  data?: ResponseError | CreatedToken;
  getExpirationDate?: ({ value, unit }) => string;
  isMutating: boolean;
}

const useCreateToken = (): UseCreateToken => {
  const { toIsoString } = useLocaleDateTimeFormat();

  const { data, mutateAsync, isMutating } = useMutationQuery<
    CreatedToken,
    undefined
  >({
    decoder: createdTokenDecoder,
    getEndpoint: () => createTokenEndpoint,
    method: Method.POST
  });

  const getExpirationDate = ({ value, unit }): string => {
    const formattedDate = dayjs().add(value, unit).toDate();

    return toIsoString(formattedDate);
  };

  const createToken = ({
    tokenName,
    duration,
    user,
    customizeDate
  }: Required<CreateTokenFormValues>): void => {
    if (equals(duration?.id, 'customize')) {
      const expirationDate = toIsoString(customizeDate as Date);

      mutateAsync({
        payload: {
          expiration_date: expirationDate,
          name: tokenName,
          user_id: user?.id
        }
      });

      return;
    }
    const durationItem = dataDuration.find(({ id }) => id === duration?.id);

    const expirationDate = getExpirationDate({
      unit: durationItem?.unit,
      value: durationItem?.value
    });

    mutateAsync({
      payload: {
        expiration_date: expirationDate,
        name: tokenName,
        user_id: user?.id
      }
    });
  };

  return {
    createToken,
    data,
    getExpirationDate,
    isMutating
  };
};

export default useCreateToken;
