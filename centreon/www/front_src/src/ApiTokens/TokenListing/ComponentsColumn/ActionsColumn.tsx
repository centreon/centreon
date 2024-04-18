import { useAtomValue } from 'jotai';
import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { useTranslation } from 'react-i18next';

import { Method } from '@centreon/ui';

import { clickedRowAtom } from '../atoms';
import { deleteSingleTokenEndpoint } from '../../api/endpoints';
import { labelMsgConfirmationDeletionToken } from '../../translatedLabels';

import Deletion from './Deletion';
import ConfirmationDeletionModal from './Deletion/ConfirmationDeletionModal';
import Message from './Deletion/Message';

const ActionsColumn = (): JSX.Element => {
  const { t } = useTranslation();
  const clickedRow = useAtomValue(clickedRowAtom);
  const meta = !clickedRow
    ? undefined
    : {
        tokenName: clickedRow.name,
        userId: clickedRow.user?.id
      };

  const dataApi = {
    dataMutation: { _meta: meta },
    getEndpoint: deleteSingleTokenEndpoint,
    method: Method.DELETE
  };

  const body = parse(
    DOMPurify.sanitize(
      t(labelMsgConfirmationDeletionToken, { tokenName: clickedRow?.name })
    )
  );

  return (
    <Deletion
      renderModalConfirmation={({ close }) => (
        <ConfirmationDeletionModal
          close={close}
          dataApi={dataApi}
          msg={<Message body={body} />}
        />
      )}
    />
  );
};
export default ActionsColumn;
