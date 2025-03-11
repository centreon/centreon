import dayjs from 'dayjs';
import { equals } from 'ramda';

import {
  Method,
  ResponseError,
  useLocaleDateTimeFormat,
  useMutationQuery
} from '@centreon/ui';
import { createdTokenDecoder } from '../api/decoder';
import { createTokenEndpoint } from '../api/endpoints';

import { CreatedToken, dataDuration } from './models';

interface UseCreateToken {
  createToken: (dataForm, { setSubmitting }) => void;
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

  const createToken = (dataForm, { setSubmitting }): void => {
    const { duration, tokenName, user, customizeDate } = dataForm;

    const durationItem = dataDuration.find(({ id }) => id === duration?.id);

    const expirationDate = equals(duration?.id, 'customize')
      ? toIsoString(customizeDate as Date)
      : getExpirationDate({
          unit: durationItem?.unit,
          value: durationItem?.value
        });

    mutateAsync({
      payload: {
        expiration_date: expirationDate,
        name: tokenName,
        user_id: user?.id
      }
    }).finally(() => setSubmitting(false));
  };

  return {
    createToken,
    data,
    getExpirationDate,
    isMutating
  };
};

export default useCreateToken;
