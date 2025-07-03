import {
  complement,
  includes,
  isEmpty,
  isNil,
  last,
  length,
  prop,
  propEq,
  split
} from 'ramda';
import useSnackbar from '../Snackbar/useSnackbar';

const useBulkResponse = () => {
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const handleBulkResponse = ({
    data,
    labelSuccess,
    labelWarning,
    labelFailed,
    items
  }) => {
    const successfullResponses =
      data?.filter(propEq(204, 'status')) || isNil(data);

    const failedResponses = data?.filter(complement(propEq(204, 'status')));

    const failedResponsesIds = failedResponses
      ?.map(prop('href'))
      ?.map((item: string) =>
        Number.parseInt(last(split('/', item || '')) as string, 10)
      );

    if (isEmpty(successfullResponses)) {
      showErrorMessage(labelFailed);

      return;
    }

    if (length(successfullResponses) < length(data)) {
      const failedResponsesNames = items
        ?.filter((item) => includes(item.id, failedResponsesIds))
        .map((item) => item.name);

      showWarningMessage(`${labelWarning}: ${failedResponsesNames.join(', ')}`);

      return;
    }

    showSuccessMessage(labelSuccess);
  };

  return handleBulkResponse;
};

export default useBulkResponse;
