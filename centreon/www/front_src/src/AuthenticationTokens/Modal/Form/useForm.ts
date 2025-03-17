import dayjs from 'dayjs';

import {
  Method,
  useLocaleDateTimeFormat,
  useMutationQuery
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect } from 'react';
import { createdTokenDecoder } from '../../api/decoder';
import { createTokenEndpoint } from '../../api/endpoints';
import { tokenAtom } from '../../atoms';
import { TokenType } from '../../models';
import { CreatedToken } from '../models';
import { dataDuration } from '../utils';

interface UseFormState {
  createToken: (values, { setSubmitting }) => void;
}

const useForm = (): UseFormState => {
  const { toIsoString } = useLocaleDateTimeFormat();

  const queryClient = useQueryClient();

  const currentUser = useAtomValue(userAtom);
  const setToken = useSetAtom(tokenAtom);

  const { data, mutateAsync } = useMutationQuery<CreatedToken, undefined>({
    decoder: createdTokenDecoder,
    getEndpoint: () => createTokenEndpoint,
    method: Method.POST
  });

  useEffect(() => {
    if (data) {
      setToken(data.token);
    }
  }, [data]);

  const getExpirationDate = ({ value, unit }): string => {
    const formattedDate = dayjs().add(value, unit).toDate();

    return toIsoString(formattedDate);
  };

  const createToken = (values, { setSubmitting }): void => {
    const { duration, name, user, customizeDate, type } = values;

    const durationItem = dataDuration.find(({ id }) => id === duration?.id);

    const getExpirationDateForApi = () => {
      if (equals(duration?.id, 'neverExpire')) {
        return null;
      }

      if (equals(duration?.id, 'customize')) {
        return toIsoString(customizeDate as Date);
      }

      return getExpirationDate({
        unit: durationItem?.unit,
        value: durationItem?.value
      });
    };

    mutateAsync({
      payload: {
        expiration_date: getExpirationDateForApi(),
        name,
        user_id: equals(type.id, TokenType.API) ? user.id : currentUser.id,
        type: type.id
      }
    }).finally(() => {
      setSubmitting(false);

      queryClient.invalidateQueries({ queryKey: ['listTokens'] });
    });
  };

  return {
    createToken
  };
};

export default useForm;
