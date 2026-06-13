import Markdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  content: {
    '& > :first-child': {
      marginTop: 0
    },
    '& > :last-child': {
      marginBottom: 0
    },
    '& a': {
      color: theme.palette.primary.main
    },
    '& code': {
      backgroundColor: theme.palette.action.hover,
      borderRadius: theme.shape.borderRadius,
      fontSize: '0.85em',
      padding: theme.spacing(0, 0.5)
    },
    '& h1, & h2, & h3, & h4, & h5, & h6': {
      ...theme.typography.subtitle2,
      fontWeight: theme.typography.fontWeightBold,
      margin: theme.spacing(1.5, 0, 0.5)
    },
    '& li': {
      marginBottom: theme.spacing(0.25)
    },
    '& p': {
      margin: theme.spacing(0.5, 0)
    },
    '& table': {
      borderCollapse: 'collapse'
    },
    '& td, & th': {
      border: `1px solid ${theme.palette.divider}`,
      padding: theme.spacing(0.25, 1)
    },
    '& ul, & ol': {
      margin: theme.spacing(0.5, 0),
      paddingLeft: theme.spacing(2.5)
    },
    ...theme.typography.body2,
    color: theme.palette.text.primary
  }
}));

interface Props {
  content: string;
}

export const MarkdownContent = ({ content }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.content}>
      <Markdown remarkPlugins={[remarkGfm]}>{content}</Markdown>
    </div>
  );
};
