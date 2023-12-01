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
  data: ResponseError | CreatedToken;
  values: CreateTokenFormValues;
}

const useCreateTokenFormValues = ({
  values,
  data
}: Props): UseCreateTokenFormValues => {
  const { token, durationValue, tokenNameValue } = useMemo(() => {
    const currentData = data as CreatedToken;
    const invalidData = data as ResponseError;

    if (!currentData?.token || invalidData?.isError) {
      return {
        durationValue: values.duration,
        token: values?.token,
        tokenNameValue: values.tokenName
      };
    }
    const tokenValue = currentData.token;

    const currentTokenName = currentData.name;

    const endDate = dayjs(currentData.expiration_date);
    const startDate = dayjs(currentData.creation_date);

    const numberOfDays = endDate.diff(startDate, UnitDate.Day);

    if (numberOfDays <= maxDays) {
      const durationName = `${numberOfDays} days`;

      return {
        durationValue: {
          id: durationName.trim(),
          name: durationName
        },
        token: tokenValue,
        tokenNameValue: currentTokenName
      };
    }
    const durationName = startDate.from(endDate, true);

    return {
      durationValue: { id: durationName.trim(), name: durationName },
      token: tokenValue,
      tokenNameValue: currentTokenName
    };
  }, [
    (data as CreatedToken)?.token,
    values.token,
    values.duration,
    values.tokenName
  ]);

  return { durationValue, token, tokenNameValue };
};

export default useCreateTokenFormValues;
