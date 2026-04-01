import IconComment from '@mui/icons-material/Comment';
import { useTheme } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelComment } from '../../../../../translatedLabels';
import { Props } from '..';
import EventAnnotations from '../EventAnnotations';

const CommentAnnotations = (props: Props): JSX.Element => {
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
