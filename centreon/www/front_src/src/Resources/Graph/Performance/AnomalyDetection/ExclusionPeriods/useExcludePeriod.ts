import { equals } from 'ramda';

import { getData, useRequest, postData, useSnackbar } from '@centreon/ui';

import { getExcludePeriodEndPoint } from '../anomalyDetectionEndPoints';

interface ExcludePeriod {
  callBack?: () => void;
  comment?: string;
  endDate: Date | string;
  periodEnd?: Date | string;
  periodStart?: Date | string;
  serviceId: number;
  startDate: Date | string;
}

const useExcludePeriod = ({
  comment,
  endDate,
  startDate,
  callBack,
  serviceId,
  periodEnd,
  periodStart
}: ExcludePeriod): any => {
  const { showErrorMessage, showSuccessMessage } = useSnackbar();

  const { sendRequest: sendActionExcludePeriod } = useRequest<any>({
    request: postData
  });
  const { sendRequest: getListExcludedPeriods } = useRequest<any>({
    request: getData
  });
  const endpoint = getExcludePeriodEndPoint({
    anomalyDetectionServiceId: serviceId
  });
  const getExcludedPeriods = (): void => {
    getListExcludedPeriods({
      endpoint: `${endpoint}?periodStart=${periodStart}&periodEnd=${periodEnd}`
    }).then((data) => {
      if (!equals(data?.code, 200)) {
        showErrorMessage(data?.message);
      }
      // mettre a jour exclusion period data atom
    });
  };
  sendActionExcludePeriod({
    data: {
      description: comment,
      endTime: endDate,
      startTime: startDate
    },
    endpoint
  }).then((data) => {
    if (!equals(data?.code, 200)) {
      showErrorMessage(data?.message);

      return;
    }
    callBack?.();
    setTimeout(() => {
      getExcludedPeriods();
    }, 700);
  });
  // setTimeOUt getNew data
};

export default useExcludePeriod;
