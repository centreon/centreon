import { makeStyles } from 'tss-react/mui';

import { Typography, Box } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  headerContainer: {
    display: 'flex',
    justifyContent: 'space-between'
  },
  itemContainer: {
    borderRadius: '5px',
    margin: theme.spacing(3),
    padding: theme.spacing(1),
    textAlign: 'center'
  }
}));

const TypographyStory = (): JSX.Element => {
  const { classes } = useStyles();

  const variants = [
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'subtitle1',
    'subtitle2',
    'body1',
    'body2',
    'button',
    'caption',
    'overline'
  ];

  return (
    <Box sx={{ width: '100%' }}>
      <Box className={classes.itemContainer}>
        {variants.map((typographyVariant) => (
          <div className={classes.headerContainer} key={typographyVariant}>
            <Typography
              gutterBottom
              display="block"
              variant={typographyVariant}
            >
              {typographyVariant}
            </Typography>
          </div>
        ))}
      </Box>
    </Box>
  );
};

export default TypographyStory;
