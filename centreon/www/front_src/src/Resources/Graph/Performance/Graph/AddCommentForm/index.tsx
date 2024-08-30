import { ChangeEvent, useState } from 'react';

import { isEmpty, isNil, pipe, trim } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Grid, Typography } from '@mui/material';

import {
  Dialog,
  TextField,
  useLocaleDateTimeFormat,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import { commentResources } from '../../../../Actions/api';
import { ResourceDetails } from '../../../../Details/models';
import { Resource } from '../../../../models';
import {
  labelAdd,
  labelAddComment,
  labelComment,
  labelCommentAdded,
  labelRequired
} from '../../../../translatedLabels';

interface Props {
  date: Date;
  onClose: () => void;
  onSuccess: (comment) => void;
  resource: Resource | ResourceDetails;
}

const AddCommentForm = ({
  onClose,
  onSuccess,
  resource,
  date
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { toIsoString, toDateTime } = useLocaleDateTimeFormat();
  const { showSuccessMessage } = useSnackbar();
  const [comment, setComment] = useState<string>();

  const { sendRequest, sending } = useRequest({
    request: commentResources
  });

  const changeComment = (event: ChangeEvent<HTMLInputElement>): void => {
    setComment(event.target.value);
  };

  const confirm = (): void => {
    const parameters = {
      comment,
      date: toIsoString(date)
    };

    sendRequest({
      parameters,
      resources: [resource]
    }).then(() => {
      showSuccessMessage(t(labelCommentAdded));
      onSuccess(parameters);
    });
  };

  const getError = (): string | undefined => {
    if (isNil(comment)) {
      return undefined;
    }

    const normalizedComment = comment || '';

    return pipe(trim, isEmpty)(normalizedComment)
      ? t(labelRequired)
      : undefined;
  };

  const canConfirm = isNil(getError()) && !isNil(comment) && !sending;

  return (
    <Dialog
      open
      confirmDisabled={!canConfirm}
      labelConfirm={t(labelAdd)}
      labelTitle={t(labelAddComment)}
      submitting={sending}
      onCancel={onClose}
      onClose={onClose}
      onConfirm={confirm}
    >
      <Grid container direction="column" spacing={2}>
        <Grid item>
          <Typography variant="h6">{toDateTime(date)}</Typography>
        </Grid>
        <Grid item>
          <TextField
            autoFocus
            multiline
            required
            ariaLabel={t(labelComment)}
            error={getError()}
            label={t(labelComment)}
            rows={3}
            style={{ width: 300 }}
            value={comment}
            onChange={changeComment}
          />
        </Grid>
      </Grid>
    </Dialog>
  );
};

export default AddCommentForm;
