import {
  SaveButton as Button,
  Method,
  TextField,
  useLocaleDateTimeFormat,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { isNil } from 'ramda';
import { ChangeEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

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
    const [data] = resources.map(({ type, id, parent }) => {
      const parentData = !isNil(parent?.id) ? { id: parent?.id } : null;

      return {
        id,
        parent: parentData,
        type
      };
    });

    const payload = [
      {
        ...data,
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
        ariaLabel={t(labelComment)}
        autoComplete="off"
        autoFocus
        label={t(labelComment)}
        multiline
        onChange={changeComment}
        required
        rows={3}
        sx={{ marginTop: 1.5, width: '100%' }}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'data-testid': 'commentArea'
            }
          }
        }}
        value={comment}
      />
      <div className={classes.footer}>
        <Button
          data-testid={labelCancel}
          labelSave={t(labelCancel)}
          onClick={cancel}
          startIcon={false}
          variant="text"
        />
        <Button
          data-testid={labelSave}
          disabled={!comment}
          labelSave={t(labelSave)}
          loading={isMutating}
          onClick={sendComment}
          startIcon={false}
          variant="outlined"
        />
      </div>
    </>
  );
};

export default AddCommentArea;
