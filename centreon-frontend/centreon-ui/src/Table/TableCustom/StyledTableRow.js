import TableRow from '@material-ui/core/TableRow';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  root: {
    '&:nth-of-type(odd)': {
      backgroundColor: '#f0fbff',
    },
    '&:nth-of-type(even)': {
      backgroundColor: '#fff',
    },
    '&:hover': {
      backgroundColor: '#cae6f1 !important',
    },
    cursor: 'pointer',
  },
};

export default withStyles(styles)(TableRow);
