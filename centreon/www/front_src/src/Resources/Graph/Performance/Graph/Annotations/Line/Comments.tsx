import { useTranslation } from 'react-i18next';

import IconComment from '@mui/icons-material/Comment';
import { useTheme } from '@mui/material';

import { Props } from '..';
import { labelComment } from '../../../../../translatedLabels';
import EventAnnotations from '../EventAnnotations';

const CommentAnnotations = (props: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  return (
    <EventAnnotations
      Icon={IconComment}
      ariaLabel={t(labelComment)}
      color={theme.palette.primary.main}
      type="comment"
      {...props}
    />
  );
};

export default CommentAnnotations;
