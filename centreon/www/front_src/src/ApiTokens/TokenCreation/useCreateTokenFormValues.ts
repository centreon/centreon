import { useMemo } from 'react';

import dayjs from 'dayjs';

import { ResponseError } from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';

import {
  CreatedToken,
  UnitDate,
  UseCreateTokenFormValues,
  maxDays
} from './models';

interface Props {
  data?: ResponseError | CreatedToken;
  values: CreateTokenFormValues;
}

const useCreateTokenFormValues = ({
  values,
  data
}: Props): UseCreateTokenFormValues => {
  const { token, duration, tokenName, user } = useMemo(() => {
    const currentData = data as CreatedToken;
    const invalidData = data as ResponseError;

    if (!currentData?.token || invalidData?.isError) {
      return {
        duration: values.duration,
        token: undefined,
        tokenName: values.tokenName,
        user: values.user
      };
    }
    const tokenValue = currentData.token;
    const tokenNameValue = currentData.name;
    const userValue = currentData.user;

    const endDate = dayjs(currentData.expiration_date);
    const startDate = dayjs(currentData.creation_date);
    const numberOfDays = endDate.diff(startDate, UnitDate.Day);

    if (numberOfDays <= maxDays) {
      const durationName = `${numberOfDays} days`;

      return {
        duration: {
          id: durationName.trim(),
          name: durationName
        },
        token: tokenValue,
        tokenName: tokenNameValue,
        user: userValue
      };
    }

    const durationName = startDate.from(endDate, true);

    return {
      duration: { id: durationName.trim(), name: durationName },
      token: tokenValue,
      tokenName: tokenNameValue,
      user: userValue
    };
  }, [
    (data as CreatedToken)?.token,
    values.user,
    values.duration,
    values.tokenName
  ]);

  return { duration, token, tokenName, user };
};

export default useCreateTokenFormValues;
