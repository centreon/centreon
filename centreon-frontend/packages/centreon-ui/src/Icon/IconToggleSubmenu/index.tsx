import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles((theme) => ({
  icon: {
    color: theme.palette.common.white,
    cursor: 'pointer',
    fontSize: theme.typography.body1.fontSize,
  },
}));

interface Props {
  onClick: () => void;
  rotate: boolean;
}

const IconToggleSubmenu = ({ rotate, onClick }: Props): JSX.Element => {
  const classes = useStyles();

  const ExpandIcon = rotate ? ExpandLessIcon : ExpandMoreIcon;

  return <ExpandIcon className={classes.icon} onClick={onClick} />;
};

export default IconToggleSubmenu;
