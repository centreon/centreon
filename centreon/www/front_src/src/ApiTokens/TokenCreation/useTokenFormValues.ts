import { useMemo } from 'react';

import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { equals } from 'ramda';

import { ResponseError, SelectEntry } from '@centreon/ui';

import { CreateTokenFormValues } from '../TokenListing/models';

import {
  CreatedToken,
  UnitDate,
  UseCreateTokenFormValues,
  maxDays
} from './models';

dayjs.extend(relativeTime);

interface Props {
  data?: ResponseError | CreatedToken;
  values: CreateTokenFormValues;
}

const useTokenFormValues = ({
  values,
  data
}: Props): UseCreateTokenFormValues => {
  const formatLabelDuration = (label: string): string => {
    return label
      .split(' ')
      .map((item) => (equals(item, 'a') ? 1 : item))
      .join(' ');
  };

  const getDuration = ({
    startTime,
    endTime,
    isCustomizeDate
  }): SelectEntry => {
    const endDate = dayjs(endTime);
    const startDate = dayjs(startTime);

    if (isCustomizeDate) {
      const name = formatLabelDuration(endDate.to(startDate, true));

      return { id: 'customize', name };
    }

    const numberOfDays = endDate.diff(startDate, UnitDate.Day);

    if (numberOfDays <= maxDays) {
      const durationName = `${numberOfDays} days`;

      return {
        id: durationName.trim(),
        name: durationName
      };
    }
    const name = formatLabelDuration(endDate.to(startDate, true));

    return { id: name.trim(), name };
  };

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

    const durationValue = getDuration({
      endTime: currentData.expirationDate,
      isCustomizeDate: equals(values?.duration?.id, 'customize'),
      startTime: currentData.creationDate
    });

    return {
      duration: durationValue,
      token: tokenValue,
      tokenName: tokenNameValue,
      user: userValue
    };
  }, [
    (data as CreatedToken)?.token,
    values.user,
    values.duration?.name,
    values.tokenName
  ]);

  return { duration, token, tokenName, user };
};

export default useTokenFormValues;
