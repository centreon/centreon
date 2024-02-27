import { ChangeEvent, useState } from 'react';

import { useTranslation } from 'react-i18next';

import {
  SaveButton as Button,
  Method,
  TextField,
  useLocaleDateTimeFormat,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { commentEndpoint } from '../../../../Actions/api/endpoint';
import {
  labelCancel,
  labelComment,
  labelSave,
  labelYourCommentSent
} from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';

import { useStyles } from './addComment.styles';

interface Props {
  closeCommentArea: () => void;
  resources: Array<ResourceDetails>;
}

const AddCommentArea = ({
  resources,
  closeCommentArea
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { toIsoString } = useLocaleDateTimeFormat();
  const { showSuccessMessage } = useSnackbar();

  const [comment, setComment] = useState('');

  const displaySnackbarAndClose = (): void => {
    showSuccessMessage(t(labelYourCommentSent));
    closeCommentArea();
  };

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: () => commentEndpoint,
    method: Method.POST,
    onSuccess: displaySnackbarAndClose
  });

  const changeComment = (event: ChangeEvent<HTMLInputElement>): void => {
    setComment(event?.target?.value);
  };

  const cancel = (): void => {
    setComment('');
    closeCommentArea();
  };

  const sendComment = (): void => {
    const date = toIsoString(new Date());

    const payload = [
      {
        ...resources.map(({ type, id, parent }) => ({
          id,
          parent: { id: parent?.id },
          type
        }))[0],
        comment,
        date
      }
    ];
    mutateAsync({
      payload: {
        resources: payload
      }
    });
  };

  return (
    <>
      <TextField
        autoFocus
        multiline
        required
        ariaLabel={t(labelComment)}
        autoComplete="off"
        inputProps={{
          'data-testid': 'commentArea'
        }}
        label={t(labelComment)}
        rows={3}
        sx={{ marginTop: 1.5, width: '100%' }}
        value={comment}
        onChange={changeComment}
      />
      <div className={classes.footer}>
        <Button
          data-testid={labelCancel}
          labelSave={t(labelCancel)}
          startIcon={false}
          variant="text"
          onClick={cancel}
        />
        <Button
          data-testid={labelSave}
          disabled={!comment}
          labelSave={t(labelSave)}
          loading={isMutating}
          startIcon={false}
          variant="outlined"
          onClick={sendComment}
        />
      </div>
    </>
  );
};

export default AddCommentArea;
