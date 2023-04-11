import { makeStyles } from 'tss-react/mui';

import IconCheck from '@mui/icons-material/CheckOutlined';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Popper from '@mui/material/Popper';
import ListItem from '@mui/material/ListItem';

import {
  labelCheck,
  labelCheckDescription,
  labelForcedCheck,
  labelForcedCheckDescription
} from '../../../translatedLabels';

import Text from './Text';

const useStyles = makeStyles()((theme) => ({
  button: {
    alignItems: 'flex-start'
  },
  container: {
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    minWidth: theme.spacing(13.5),
    padding: theme.spacing(0, 1)
  },
  icon: {
    minWidth: theme.spacing(3)
  },
  itemText: {
    '& .MuiTypography-root': {
      width: '100%'
    },
    display: 'flex',
    flexDirection: 'row-reverse',
    margin: 0,
    paddingRight: theme.spacing(0.5)
  },
  popover: {
    zIndex: theme.zIndex.fab
  }
}));

interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

interface Props {
  anchorEl?: HTMLElement | null;
  disabled: Disabled;
  isDefaultChecked: boolean;
  onClickCheck?: () => void;
  onClickForcedCheck?: () => void;
  open: boolean;
}

interface PropsIcon {
  display: boolean;
}

const Icon = ({ display }: PropsIcon): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ListItemIcon className={classes.icon}>
      {display && <IconCheck color="success" fontSize="small" />}
    </ListItemIcon>
  );
};

const CheckOptionsList = ({
  open,
  anchorEl,
  isDefaultChecked,
  onClickForcedCheck,
  onClickCheck,
  disabled
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { disableForcedCheck, disableCheck } = disabled;

  return (
    <Popper
      anchorEl={anchorEl}
      className={classes.popover}
      open={open}
      placement="bottom-start"
    >
      <List disablePadding className={classes.container}>
        <ListItem disableGutters disablePadding>
          <ListItemButton
            disableGutters
            className={classes.button}
            disabled={disableCheck}
            onClick={onClickCheck}
          >
            <Icon display={isDefaultChecked} />
            <ListItemText
              className={classes.itemText}
              primary={
                <Text description={labelCheckDescription} title={labelCheck} />
              }
            />
          </ListItemButton>
        </ListItem>
        <Divider variant="middle" />
        <ListItem disableGutters disablePadding>
          <ListItemButton
            disableGutters
            className={classes.button}
            disabled={disableForcedCheck}
            onClick={onClickForcedCheck}
          >
            <Icon display={!isDefaultChecked} />

            <ListItemText
              className={classes.itemText}
              primary={
                <Text
                  description={labelForcedCheckDescription}
                  title={labelForcedCheck}
                />
              }
            />
          </ListItemButton>
        </ListItem>
      </List>
    </Popper>
  );
};

export default CheckOptionsList;
