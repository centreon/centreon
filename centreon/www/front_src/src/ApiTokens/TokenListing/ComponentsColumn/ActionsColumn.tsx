import { useAtomValue } from 'jotai';

import { clickedRowAtom } from '../atoms';
import { deleteSingleTokenEndpoint } from '../../api/endpoints';

import Deletion from './Deletion';
import ConfirmationDeletionModal from './Deletion/ConfirmationDeletionModal';
import Message from './Deletion/Message';

const ActionsColumn = (): JSX.Element => {
  const clickedRow = useAtomValue(clickedRowAtom);
  const meta = !clickedRow
    ? undefined
    : {
        tokenName: clickedRow.name,
        userId: clickedRow.user?.id
      };

  return (
    <Deletion
      renderModalConfirmation={({ close }) => (
        <ConfirmationDeletionModal
          close={close}
          dataMutation={{ _meta: meta }}
          getEndpoint={deleteSingleTokenEndpoint}
          msg={<Message />}
        />
      )}
    />
  );
};
export default ActionsColumn;
