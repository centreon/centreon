import { ReactNode, useState } from 'react';

import { useTranslation } from 'react-i18next';

import {
  ConfirmDialog,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { Meta } from '../../../api/models';
import {
  labelCancel,
  labelDelete,
  labelTokenDeletedSuccessfully
} from '../../../translatedLabels';
import useRefetch from '../../../useRefetch';

import Title from './Title';
import { useStyles } from './deletion.styles';

interface DataMutation {
  _meta?: Meta;
  payload?: Array<{ token_name: string; user_id: number }>;
}

interface Props {
  close: () => void;
  dataMutation?: DataMutation;
  getEndpoint: (data: Meta) => string;
  labelDeletedSuccessfully?: string;
  msg?: ReactNode;
  title?: string;
}

const ConfirmationDeletionModal = ({
  close,
  getEndpoint,
  dataMutation,
  labelDeletedSuccessfully = labelTokenDeletedSuccessfully,
  title,
  msg
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const [isRetrievingData, setIsRetrievingData] = useState(false);
  const { isRefetching } = useRefetch({
    key: isRetrievingData,
    onSuccess: () => close()
  });

  const { _meta, payload } = dataMutation || {};

  const success = (): void => {
    showSuccessMessage(t(labelDeletedSuccessfully));
    setIsRetrievingData(true);
  };

  const { mutateAsync, isMutating } = useMutationQuery<object, Meta>({
    getEndpoint,
    method: Method.DELETE,
    onSuccess: success
  });

  const deleteToken = (): void => {
    mutateAsync({ _meta, payload });
  };

  const disabled = isMutating || isRefetching;

  return (
    <ConfirmDialog
      open
      cancelDisabled={disabled}
      confirmDisabled={disabled}
      data-testid="deleteDialog"
      dialogTitleClassName={classes.title}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelTitle={<Title title={title} />}
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
      {msg}
    </ConfirmDialog>
  );
};

export default ConfirmationDeletionModal;
