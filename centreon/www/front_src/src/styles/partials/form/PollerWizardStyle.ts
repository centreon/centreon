import { makeStyles } from 'tss-react/mui';

import { grey } from '@mui/material/colors';

interface StylesProps {
  statusCreating: boolean | null;
  statusGenerating: boolean | null;
}

const useStyles = makeStyles()((theme) => ({
  form: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(2),
    width: '100%'
  },
  formButton: {
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: theme.spacing(1.875)
  },
  formHeading: {
    marginBottom: theme.spacing(0.625)
  },
  formItem: {
    paddingBottom: theme.spacing(1.875)
  },
  formText: {
    color: '#242f3a',
    fontFamily: 'Roboto Regular',
    fontSize: theme.spacing(1.5),
    margin: '20px 0'
  },
  wizardRadio: {
    columnGap: theme.spacing(2),
    display: 'flex',
    justifyContent: 'center',
    marginBottom: theme.spacing(3)
  }
}));

const useStylesWithProps = makeStyles<StylesProps>()(
  (theme, { statusCreating, statusGenerating }) => ({
    formButton: {
      columnGap: theme.spacing(1),
      display: 'flex',
      flexDirection: 'row',
      justifyContent: 'flex-end',
      marginTop: theme.spacing(1.875)
    },
    formHeading: {
      marginBottom: theme.spacing(0.625)
    },
    formText: {
      color: grey[500],
      fontFamily: 'Roboto Regular',
      fontSize: theme.spacing(1.5),
      margin: '20px 0'
    },
    statusCreating: {
      color: statusCreating ? '#acd174' : '#d0021b'
    },
    statusGenerating: {
      color: statusGenerating ? '#acd174' : '#d0021b'
    }
  })
);

export { useStyles, useStylesWithProps };
