import { makeStyles } from 'tss-react/mui';

import IconCheck from '@mui/icons-material/CheckOutlined';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Popover from '@mui/material/Popover';
import Divider from '@mui/material/Divider';

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
    padding:theme.spacing(0,1,0,1)
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
  }
}));

interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

enum VerticalEnum {
  bottom = 'bottom',
  center = 'center',
  top = 'top'
}

enum HorizontalEnum {
  center = 'center',
  left = 'left',
  right = 'right'
}

interface AnchorOrigin {
  horizontal: HorizontalEnum;
  vertical: VerticalEnum;
}

interface TransformOrigin {
  horizontal: HorizontalEnum;
  vertical: VerticalEnum;
}

interface Props {
  anchorEl?: HTMLElement | null;
  anchorOrigin?: AnchorOrigin;
  disabled: Disabled;
  isDefaultChecked: boolean;
  onClickCheck: () => void;
  onClickForcedCheck: () => void;
  onClose: () => void;
  open: boolean;
  transformOrigin?: TransformOrigin;
}

const defaultAnchorOrigin = {
  horizontal: HorizontalEnum.left,
  vertical: VerticalEnum.bottom
};

const defaultTransformOrigin = {
  horizontal: HorizontalEnum.left,
  vertical: VerticalEnum.top
};

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

const ListCheckOptions = ({
  open,
  onClose,
  anchorOrigin = defaultAnchorOrigin,
  transformOrigin = defaultTransformOrigin,
  anchorEl,
  isDefaultChecked,
  onClickForcedCheck,
  onClickCheck,
  disabled
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { disableForcedCheck, disableCheck } = disabled;

  return (
    <Popover
      anchorEl={anchorEl}
      anchorOrigin={anchorOrigin}
      open={open}
      transformOrigin={transformOrigin}
      onClose={onClose}
    >
      <List disablePadding className={classes.container}>
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
        <Divider variant="middle" />
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
      </List>
    </Popover>
  );
};

export default ListCheckOptions;
