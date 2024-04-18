import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Method } from '@centreon/ui';

import { deleteMultipleTokensEndpoint } from '../../../api/endpoints';
import Deletion from '../../ComponentsColumn/Deletion';
import ConfirmationDeletionModal from '../../ComponentsColumn/Deletion/ConfirmationDeletionModal';
import { selectedRowsAtom } from '../../atoms';
import {
  labelDeleteSelectedTokens,
  labelMsgConfirmationDeletionTokens
} from '../../../translatedLabels';
import { deletedTokensDecoder } from '../../../api/decoder';
import Message from '../../ComponentsColumn/Deletion/Message';

const Delete = (): JSX.Element => {
  const { t } = useTranslation();
  const selectedRows = useAtomValue(selectedRowsAtom);
  const payload = {
    resources: selectedRows.map((item) => ({
      token_name: item.name,
      user_id: item.user.id
    }))
  };

  const dataApi = {
    dataMutation: { payload },
    decoder: deletedTokensDecoder,
    getEndpoint: deleteMultipleTokensEndpoint,
    method: Method.POST
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
