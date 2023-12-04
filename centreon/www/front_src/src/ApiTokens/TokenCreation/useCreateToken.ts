import dayjs from 'dayjs';

import {
  Method,
  ResponseError,
  useLocaleDateTimeFormat,
  useMutationQuery
} from '@centreon/ui';

import { createTokenEndpoint } from '../api/endpoints';
import useRefetch from '../useRefetch';
import { CreateTokenFormValues } from '../TokenListing/models';

import { CreatedToken, dataDuration } from './models';

interface UseCreateToken {
  createToken: (params: Required<CreateTokenFormValues>) => void;
  data?: ResponseError | CreatedToken;
  isMutating: boolean;
}

const useCreateToken = (): UseCreateToken => {
  const { toIsoString } = useLocaleDateTimeFormat();

  const { data, mutateAsync, isMutating } = useMutationQuery<
    CreatedToken,
    undefined
  >({
    getEndpoint: () => createTokenEndpoint,
    method: Method.POST
  });

  useRefetch((data as CreatedToken)?.token);

  const getExpirationDate = ({ value, unit }): string => {
    const formattedDate = dayjs().add(value, unit).toDate();

    return toIsoString(formattedDate);
  };

  const createToken = ({
    tokenName,
    duration,
    user
  }: Required<CreateTokenFormValues>): void => {
    const durationItem = dataDuration.find(({ id }) => id === duration?.id);

    const expirationDate = getExpirationDate({
      unit: durationItem?.unit,
      value: durationItem?.value
    });

    mutateAsync({
      expiration_date: expirationDate,
      name: tokenName,
      user_id: user?.id
    });
  };

  return {
    createToken,
    data,
    isMutating
  };
};

export default useCreateToken;
