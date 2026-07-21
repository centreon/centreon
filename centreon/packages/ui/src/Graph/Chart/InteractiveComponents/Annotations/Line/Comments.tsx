import IconComment from '@mui/icons-material/Comment';
import { useTheme } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelComment } from '../../../translatedLabels';
import EventAnnotations from '../EventAnnotations';
import type { Args } from '../models';

const CommentAnnotations = (props: Args): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  return (
    <EventAnnotations
      ariaLabel={t(labelComment)}
      color={theme.palette.primary.main}
      Icon={IconComment}
      type="comment"
      {...props}
    />
  );
};

export default CommentAnnotations;
