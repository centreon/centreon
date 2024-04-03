import { useAtomValue } from 'jotai';

import { deleteMultipleTokensEndpoint } from '../../../api/endpoints';
import Deletion from '../../ComponentsColumn/Deletion';
import ConfirmationDeletionModal from '../../ComponentsColumn/Deletion/ConfirmationDeletionModal';
import { selectedRowsAtom } from '../../atoms';
import { labelDeleteSelectedTokens } from '../../../translatedLabels';

const Delete = (): JSX.Element => {
  const selectedRows = useAtomValue(selectedRowsAtom);
  const payload = selectedRows.map((item) => ({
    token_name: item.name,
    user_id: item.user.id
  }));

  return (
    <Deletion
      disabled={selectedRows.length <= 0}
      label={labelDeleteSelectedTokens}
      renderModalConfirmation={({ close }) => (
        <ConfirmationDeletionModal
          close={close}
          dataMutation={{ payload }}
          getEndpoint={deleteMultipleTokensEndpoint}
          title={labelDeleteSelectedTokens}
        />
      )}
    />
  );
};

export default Delete;
