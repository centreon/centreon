import DOMPurify from 'dompurify';
import parse from 'html-react-parser';
import { makeStyles } from 'tss-react/mui';

import { truncate } from '@centreon/ui';
import { Typography } from '@mui/material';

type StylesProps = Pick<Props, 'bold'>;

const useStyles = makeStyles<StylesProps>()((_theme, { bold }) => ({
  information: {
    fontWeight: bold ? 600 : 'unset'
  }
}));

interface Props {
  bold?: boolean;
  content?: string;
}

const OutputInformation = ({ content, bold = false }: Props): JSX.Element => {
  const { classes } = useStyles({ bold });

  return (
    <Typography className={classes.information} variant="body2">
      {parse(DOMPurify.sanitize(truncate({ content })))}
    </Typography>
  );
};

export default OutputInformation;
