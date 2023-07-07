import { ReactElement } from 'react';

import { Link as LinkIcon } from '@mui/icons-material';

import { Button } from '../../Button';
import { useStyles } from '../Form.styles';

import { useAccessRightsForm } from './useAccessRightsForm';

export type AccessRightsFormActionsProps = {
  labels: AccessRightsFormActionsLabels;
  onCancel?: () => void;
  onCopyLink?: () => void;
};

type AccessRightsFormActionsLabels = {
  cancel: string;
  copyLink: string;
  submit: string;
};

const AccessRightsFormActions = ({
  labels,
  onCancel,
  onCopyLink
}: AccessRightsFormActionsProps): ReactElement => {
  const { classes } = useStyles();
  const { isDirty, submit } = useAccessRightsForm();

  return (
    <div className={classes.actions}>
      <span>
        <Button
          aria-label={labels.copyLink}
          data-testid="copy-link"
          icon={<LinkIcon />}
          iconVariant="start"
          size="small"
          variant="ghost"
          onClick={() => onCopyLink?.()}
        >
          {labels.copyLink}
        </Button>
      </span>
      <span>
        <Button
          aria-label={labels.cancel}
          data-testid="cancel"
          size="medium"
          variant="secondary"
          onClick={() => onCancel?.()}
        >
          {labels.cancel}
        </Button>
        <Button
          aria-label={labels.submit}
          data-testid="submit"
          disabled={!isDirty}
          size="medium"
          type="submit"
          variant="primary"
          onClick={submit}
        >
          {labels.submit}
        </Button>
      </span>
    </div>
  );
};

export { AccessRightsFormActions };
