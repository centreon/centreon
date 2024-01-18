import { useTranslation } from 'react-i18next';
import { useAtom, useAtomValue } from 'jotai';

import {
  ConfirmDialog,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  labelDeleteToken,
  labelMsgConfirmationDeletionToken,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import { clickedRowAtom } from '../../atoms';
import { deleteTokenEndpoint } from '../../../api/endpoints';

import { useStyles } from './deletion.styles';
import { displayConfirmationModalAtom } from './atoms';

interface Meta {
  name: string;
}

const ConfirmationDeletionModal = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [displayConfirmationModal, setDisplayConfirmationModal] = useAtom(
    displayConfirmationModalAtom
  );
  const clickedRow = useAtomValue(clickedRowAtom);

  const { mutateAsync, isMutating } = useMutationQuery<object, Meta>({
    getEndpoint: ({ name }) => deleteTokenEndpoint(name),
    method: Method.DELETE,
    onSuccess: () => {
      showSuccessMessage(t(labelTokenDeletedSuccessfully));
    }
  });

  const deleteToken = (): void => {
    mutateAsync({
      _meta: { name: clickedRow?.name }
    });
  };

  const cancel = (): void => {
    setDisplayConfirmationModal(false);
  };

  return (
    <ConfirmDialog
      dialogConfirmButtonClassName={classes.confirmButton}
      labelMessage={t(labelMsgConfirmationDeletionToken)}
      labelTitle={t(labelDeleteToken)}
      open={displayConfirmationModal}
      submitting={isMutating}
      onCancel={cancel}
      onConfirm={deleteToken}
    />
  );
};

export default ConfirmationDeletionModal;
