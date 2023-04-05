import { makeStyles } from 'tss-react/mui';

import { Stack, Box, useTheme, Typography, Divider } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  divider: {
    margin: theme.spacing(2)
  },
  headerContainer: {
    display: 'flex',
    justifyContent: 'space-between'
  },
  itemContainer: {
    borderRadius: '5px',
    margin: theme.spacing(2),
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  itemContainerText: {
    border: '0.3px solid',
    borderRadius: '5px',
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

  const listKeyToRemove = [
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
      <Stack>
        {Object.entries(palette[keyTheme]).map(
          ([key, value]) =>
            !listKeyToRemove.includes(key) && (
              <div className={classes.itemContainer} key={key}>
                <div className={classes.headerContainer}>
                  <Typography variant="h6">{key}</Typography>
                  <Typography variant="button">{value}</Typography>
                </div>

                <Box
                  sx={{
                    backgroundColor: value,
                    borderRadius: '5px',
                    height: 50,
                    width: '100%'
                  }}
                />
              </div>
            )
        )}
      </Stack>
    </Box>
  );
};

const GroupedColorStatus = (): JSX.Element => {
  const listStatusPalette = ['info', 'success', 'error', 'warning'];

  return (
    <>
      {listStatusPalette.map((status) => (
        <>
          <ContainerDescription
            containerTitle={status}
            key={status}
            keyTheme={status}
          />
          <Divider variant="middle" />
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
        {Object.entries(palette.text).map(([key, value]) => (
          <div className={classes.itemContainerText} key={key}>
            <div className={classes.headerContainer}>
              <Typography variant="h6">{key}</Typography>
              <Typography variant="button">{value}</Typography>
            </div>

            <Typography
              sx={{
                borderRadius: '5px',
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
  isGrouped?: boolean;
  isText?: boolean;
  paletteKey: string;
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
