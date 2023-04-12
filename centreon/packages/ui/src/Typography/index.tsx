import { makeStyles } from 'tss-react/mui';
import { toPairs } from 'ramda';

import {
  useTheme,
  Typography,
  Box,
  Stack,
  CardContent,
  Card,
  CardHeader
} from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  headerContainer: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between',
    margin: theme.spacing(3)
  },
  itemContainer: {
    borderRadius: 1,
    margin: theme.spacing(3),
    textAlign: 'center'
  }
}));

const TypographyStory = (): JSX.Element => {
  const { classes } = useStyles();
  const { typography } = useTheme();

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
          <Stack
            className={classes.headerContainer}
            direction="row"
            key={typographyVariant}
            spacing={10}
          >
            <Typography
              gutterBottom
              display="block"
              variant={typographyVariant}
            >
              {typographyVariant}
            </Typography>
            <Card sx={{ minWidth: 545 }}>
              <CardHeader title={typographyVariant} />
              <CardContent>
                {toPairs(typography[typographyVariant]).map(([key, value]) => (
                  <Box
                    key={key}
                    sx={{
                      display: 'flex',
                      justifyContent: 'space-between'
                    }}
                  >
                    <Typography color="text.secondary" variant="subtitle1">
                      {key}
                    </Typography>
                    <Typography color="text.secondary" variant="body2">
                      {value}
                    </Typography>
                  </Box>
                ))}
              </CardContent>
            </Card>
          </Stack>
        ))}
      </Box>
    </Box>
  );
};

export default TypographyStory;
