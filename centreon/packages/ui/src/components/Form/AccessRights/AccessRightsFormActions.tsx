import { ReactElement } from 'react';

import { Link as LinkIcon } from '@mui/icons-material';

import { Button } from '../../Button';
import { useStyles } from '../Form.styles';
import { useCopyToClipboard } from '../../../utils';

import { useAccessRightsForm } from './useAccessRightsForm';

export type AccessRightsFormActionsProps = {
  labels: AccessRightsFormActionsLabels;
  onCancel?: () => void;
  resourceLink: string;
};

type AccessRightsFormActionsLabels = {
  cancel: string;
  copyLink: string;
  copyLinkMessages: {
    error: string;
    success: string;
  };
  submit: string;
};

const AccessRightsFormActions = ({
  labels,
  onCancel,
  resourceLink
}: AccessRightsFormActionsProps): ReactElement => {
  const { classes } = useStyles();
  const { isDirty, submit } = useAccessRightsForm();
  const { copy } = useCopyToClipboard({
    errorMessage: labels.copyLinkMessages.error,
    successMessage: labels.copyLinkMessages.success
  });

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
          onClick={() => copy(resourceLink)}
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
