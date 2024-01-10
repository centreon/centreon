import { useTranslation } from 'react-i18next';

import IconComment from '@mui/icons-material/Comment';
import Typography from '@mui/material/Typography';

import ActionButton from '../../../../Actions/ActionButton';
import useAclQuery from '../../../../Actions/Resource/aclQuery';
import { labelAddComment } from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';

interface Props {
  onClick: () => void;
  resources: Array<ResourceDetails>;
}

const AddCommentButton = ({ resources, onClick }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { canComment } = useAclQuery();

  const hasOneResourceSelected = resources.length === 1;

  const disableAddComment = !hasOneResourceSelected || !canComment(resources);

  return (
    <ActionButton
      aria-label={t(labelAddComment) as string}
      data-testid="addComment"
      disabled={disableAddComment}
      startIcon={<IconComment />}
      variant="contained"
      onClick={onClick}
    >
      <Typography variant="body2"> {t(labelAddComment)} </Typography>
    </ActionButton>
  );
};

export default AddCommentButton;
