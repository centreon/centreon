import { useMemo } from 'react';

import { equals } from 'ramda';

import { ResponseError } from '@centreon/ui';

import { CreateTokenFormValues } from '../Listing/models';

import { CreatedToken, UseCreateTokenFormValues } from './models';
import { getDuration } from './utils';

interface Props {
  data?: ResponseError | CreatedToken;
  values: CreateTokenFormValues;
}

const useTokenFormValues = ({
  values,
  data
}: Props): UseCreateTokenFormValues => {
  const { token, duration, tokenName, user, type } = useMemo(() => {
    const currentData = data as CreatedToken;
    const invalidData = data as ResponseError;

    if (!currentData?.token || invalidData?.isError) {
      return {
        duration: values.duration,
        token: undefined,
        tokenName: values.tokenName,
        user: values.user,
        type: values.type
      };
    }
    const tokenValue = currentData.token;
    const tokenNameValue = currentData.name;
    const userValue = currentData.user;
    const type = currentData.type;

    const durationValue = getDuration({
      endTime: currentData.expirationDate,
      isCustomizeDate: equals(values?.duration?.id, 'customize'),
      startTime: currentData.creationDate
    });

    return {
      duration: durationValue,
      token: tokenValue,
      tokenName: tokenNameValue,
      user: userValue,
      type
    };
  }, [
    (data as CreatedToken)?.token,
    values.user,
    values.duration?.name,
    values.tokenName,
    values.type
  ]);

  return { duration, token, tokenName, user, type };
};

export default useTokenFormValues;
