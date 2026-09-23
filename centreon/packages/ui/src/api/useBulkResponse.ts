import {
  complement,
  includes,
  isEmpty,
  isNil,
  last,
  prop,
  propEq,
  split
} from 'ramda';

import useSnackbar from '../Snackbar/useSnackbar';

interface BulkResponseItem {
  id: number | string;
  name: string;
}

interface BulkResponseData {
  href?: string;
  status: number;
}

interface HandleBulkResponseProps {
  data: Array<BulkResponseData> | undefined;
  items: Array<BulkResponseItem>;
  labelFailed: string;
  labelSuccess: string;
  labelWarning: string;
}

const useBulkResponse = () => {
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const handleBulkResponse = ({
    data,
    labelSuccess,
    labelWarning,
    labelFailed,
    items
  }: HandleBulkResponseProps) => {
    const successfullResponses =
      data?.filter(propEq(204, 'status')) || isNil(data);

    const failedResponses = data?.filter(complement(propEq(204, 'status')));

    const failedResponsesIds = (failedResponses
      ?.map(prop('href'))
      ?.map((item) =>
        Number.parseInt(last(split('/', (item as string) || '')) as string, 10)
      ) || []) as Array<number>;

    if (isEmpty(successfullResponses)) {
      showErrorMessage(labelFailed);

      return;
    }

    const successCount = (successfullResponses as Array<BulkResponseData>)
      .length;
    const totalCount = data?.length ?? 0;
    if (successCount < totalCount) {
      const failedResponsesNames = items
        ?.filter((item: BulkResponseItem) =>
          includes(item.id, failedResponsesIds)
        )
        .map((item: BulkResponseItem) => item.name);

      showWarningMessage(`${labelWarning}: ${failedResponsesNames.join(', ')}`);

      return;
    }

    showSuccessMessage(labelSuccess);
  };

  return handleBulkResponse;
};

export default useBulkResponse;
