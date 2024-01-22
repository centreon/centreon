import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  ConfirmDialog,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { deleteTokenEndpoint } from '../../../api/endpoints';
import {
  labelCancel,
  labelDelete,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import useRefetch from '../../../useRefetch';
import { clickedRowAtom } from '../../atoms';

import Message from './Message';
import Title from './Title';
import { useStyles } from './deletion.styles';

interface Meta {
  name: string;
}

interface Props {
  close: () => void;
  open: boolean;
}

const ConfirmationDeletionModal = ({ open, close }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const [isRetrievingData, setIsRetrievingData] = useState(false);
  const { isRefetching } = useRefetch({
    key: isRetrievingData,
    onSuccess: () => close()
  });

  const clickedRow = useAtomValue(clickedRowAtom);

  const success = (): void => {
    showSuccessMessage(t(labelTokenDeletedSuccessfully));
    setIsRetrievingData(true);
  };

  const { mutateAsync, isMutating } = useMutationQuery<object, Meta>({
    getEndpoint: ({ name }) => deleteTokenEndpoint(name),
    method: Method.DELETE,
    onSuccess: success
  });

  const deleteToken = (): void => {
    mutateAsync({
      _meta: { name: clickedRow?.name }
    });
  };

  return (
    <ConfirmDialog
      cancelDisabled={isMutating || isRefetching}
      confirmDisabled={isMutating || isRefetching}
      data-testid="deleteDialog"
      dialogTitleClassName={classes.title}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelTitle={<Title />}
      open={open}
      restCancelButtonProps={{ variant: 'outlined' }}
      restConfirmButtonProps={{
        classes: { root: classes.confirmButton },
        color: 'error',
        variant: 'contained'
      }}
      submitting={isMutating}
      onCancel={close}
      onConfirm={deleteToken}
    >
      <Message />
    </ConfirmDialog>
  );
};

export default ConfirmationDeletionModal;
