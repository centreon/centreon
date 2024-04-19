import { useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { useTranslation } from 'react-i18next';

import { Method, useSnackbar } from '@centreon/ui';

import { deletedTokensDecoder } from '../../../api/decoder';
import { deleteMultipleTokensEndpoint } from '../../../api/endpoints';
import {
  labelDeleteSelectedTokens,
  labelFailedToDeleteSelectedToken,
  labelMsgConfirmationDeletionTokens,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import Deletion from '../../ComponentsColumn/Deletion';
import ConfirmationDeletionModal from '../../ComponentsColumn/Deletion/ConfirmationDeletionModal';
import Message from '../../ComponentsColumn/Deletion/Message';
import { selectedRowsAtom } from '../../atoms';
import { DeletedToken, DeletedTokens } from '../../../api/models';

const Delete = (): JSX.Element => {
  const { t } = useTranslation();
  const selectedRows = useAtomValue(selectedRowsAtom);
  const { showSuccessMessage, showWarningMessage } = useSnackbar();

  const payload = {
    resources: selectedRows.map((item) => ({
      token_name: item.name,
      user_id: item.user.id
    }))
  };

  const isFailureStatusCode = (data: DeletedToken): boolean =>
    data.status < 200 || data.status > 299;

  const extractTokenName = (data: DeletedToken): string =>
    data.self.split('/')[2];

  const onSuccess = (data: DeletedTokens): void => {
    const isFailureExist = data.results.some((result) =>
      isFailureStatusCode(result)
    );
    if (!isFailureExist) {
      showSuccessMessage(pluralize(t(labelTokenDeletedSuccessfully)));

      return;
    }

    const failedDeletedSelectedTokensNames = data.results
      .map((result) =>
        isFailureStatusCode(result) ? null : extractTokenName(result)
      )
      .filter((result) => result);

    failedDeletedSelectedTokensNames.forEach(
      (failedDeletedSelectedTokenName) => {
        showWarningMessage(
          `${labelFailedToDeleteSelectedToken}:${failedDeletedSelectedTokenName}`
        );
      }
    );
  };

  const dataApi = {
    dataMutation: { payload },
    decoder: deletedTokensDecoder,
    getEndpoint: deleteMultipleTokensEndpoint,
    method: Method.POST,
    onSuccess
  };

  return (
    <Deletion
      disabled={selectedRows.length <= 0}
      label={labelDeleteSelectedTokens}
      renderModalConfirmation={({ close }) => (
        <ConfirmationDeletionModal
          close={close}
          dataApi={dataApi}
          msg={<Message body={t(labelMsgConfirmationDeletionTokens)} />}
          title={labelDeleteSelectedTokens}
        />
      )}
    />
  );
};

export default Delete;
