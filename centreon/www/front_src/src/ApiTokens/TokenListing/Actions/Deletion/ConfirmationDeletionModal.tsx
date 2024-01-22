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
  labelMsgConfirmationDeletionToken,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import useRefetch from '../../../useRefetch';
import { clickedRowAtom } from '../../atoms';

import { useStyles } from './deletion.styles';
import Title from './Title';

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
      labelMessage={t(labelMsgConfirmationDeletionToken)}
      labelMessageProps={{
        className: classes.labelMessage,
        variant: 'subtitle1'
      }}
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
    />
  );
};

export default ConfirmationDeletionModal;
