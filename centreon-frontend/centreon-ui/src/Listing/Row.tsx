import TableRow from '@material-ui/core/TableRow';
import { withStyles, fade } from '@material-ui/core';

const styles = (theme): {} => ({
  root: {
    cursor: 'pointer',
    backgroundColor: theme.palette.common.white,
    '&:hover': {
      backgroundColor: fade(theme.palette.primary.main, 0.08),
    },
  },
});

export default withStyles(styles)(TableRow);
