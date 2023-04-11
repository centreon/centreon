import { makeStyles } from 'tss-react/mui';
import { includes, toPairs } from 'ramda';

import { Stack, Box, useTheme, Typography, Divider, Grid } from '@mui/material';
import { ThemeMode } from '@centreon/ui-context';

const useStyles = makeStyles()((theme) => ({
  divider: {
    margin: theme.spacing(2)
  },
  headerContainer: {
    display: 'flex',
    justifyContent: 'space-between'
  },
  itemContainer: {
    borderRadius: 1,
    margin: theme.spacing(2),
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  itemContainerText: {
    border: '0.3px solid',
    borderRadius: 1,
    margin: theme.spacing(2),
    padding: theme.spacing(1),
    textAlign: 'center'
  }
}));

interface ContainerDescriptionProps {
  containerTitle: string;
  keyTheme: string;
}
const ContainerDescription = ({
  keyTheme,
  containerTitle
}: ContainerDescriptionProps): JSX.Element => {
  const { classes } = useStyles();
  const { palette } = useTheme();

  const keysToRemove = [
    'contrastText',
    'activatedOpacity',
    'focusOpacity',
    'hoverOpacity',
    'selectedOpacity',
    'disabledOpacity'
  ];

  return (
    <Box sx={{ width: '100%' }}>
      <Typography variant="h4">{containerTitle}</Typography>
      <Grid container columnSpacing={{ md: 3, sm: 2, xs: 1 }} rowSpacing={5}>
        {toPairs(palette[keyTheme]).map(
          ([key, value]) =>
            !includes(key, keysToRemove) && (
              <Grid item key={key} xs={6}>
                <div className={classes.headerContainer}>
                  <Typography variant="h6">{key}</Typography>
                  <Typography variant="button">{value}</Typography>
                </div>

                <Box
                  sx={{
                    backgroundColor: value,
                    borderRadius: 1,
                    height: 50,
                    width: '100%'
                  }}
                />
              </Grid>
            )
        )}
      </Grid>
    </Box>
  );
};

const GroupedColorStatus = (): JSX.Element => {
  const { classes } = useStyles();
  const listStatusPalette = ['info', 'success', 'error', 'warning', 'pending'];

  return (
    <>
      {listStatusPalette.map((status) => (
        <>
          <ContainerDescription
            containerTitle={status}
            key={status}
            keyTheme={status}
          />
          <Divider className={classes.divider} variant="middle" />
        </>
      ))}
    </>
  );
};

interface TextColorContainerProps {
  containerTitle: string;
}

const TextColorContainer = ({
  containerTitle
}: TextColorContainerProps): JSX.Element => {
  const { classes } = useStyles();
  const { palette } = useTheme();

  return (
    <Box sx={{ width: '100%' }}>
      <Typography variant="h4">{containerTitle}</Typography>
      <Stack>
        {toPairs(palette.text).map(([key, value]) => (
          <div className={classes.itemContainerText} key={key}>
            <div className={classes.headerContainer}>
              <Typography variant="h6">{key}</Typography>
              <Typography variant="button">{value}</Typography>
            </div>

            <Typography
              sx={{
                borderRadius: 1,
                color: value,
                width: '100%'
              }}
              variant="h3"
            >
              Hello world
            </Typography>
          </div>
        ))}
      </Stack>
    </Box>
  );
};

interface ColorStoryProps {
  themeMode?: ThemeMode;
  isGrouped?: boolean;
  isText?: boolean;
  paletteKey?: string;
  title?: string;
}

export const ColorStory = ({
  paletteKey = 'primary',
  title = '',
  isGrouped = false,
  isText = false
}: ColorStoryProps): JSX.Element => {
  const displayByType = (): JSX.Element => {
    if (isText) {
      return <TextColorContainer containerTitle={title} />;
    }

    return isGrouped ? (
      <GroupedColorStatus />
    ) : (
      <ContainerDescription containerTitle={title} keyTheme={paletteKey} />
    );
  };

  return displayByType();
};
