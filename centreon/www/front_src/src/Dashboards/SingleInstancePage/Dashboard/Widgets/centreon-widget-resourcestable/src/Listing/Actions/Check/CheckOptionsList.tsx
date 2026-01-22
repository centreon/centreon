import IconCheck from '@mui/icons-material/CheckOutlined';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Popper from '@mui/material/Popper';

import { labelCheck, labelForcedCheck } from '../../translatedLabels';
import { Data } from '../model';
import { useOptionsStyles } from './check.styles';
import Text from './Text';

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
  const { classes } = useOptionsStyles();

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
  disabled,
  ...rest
}: Props & Data['listOptions']): JSX.Element => {
  const { classes } = useOptionsStyles();
  const { disableForcedCheck, disableCheck } = disabled;

  return (
    <Popper
      anchorEl={anchorEl}
      className={classes.popover}
      open={open}
      placement="bottom-start"
    >
      <List className={classes.container} disablePadding>
        <ListItem disableGutters disablePadding>
          <ListItemButton
            className={classes.button}
            disabled={disableCheck}
            disableGutters
            onClick={onClickCheck}
          >
            <Icon display={isDefaultChecked} />
            <ListItemText
              className={classes.itemText}
              primary={
                <Text description={rest.descriptionCheck} title={labelCheck} />
              }
            />
          </ListItemButton>
        </ListItem>
        <Divider variant="middle" />
        <ListItem disableGutters disablePadding>
          <ListItemButton
            className={classes.button}
            disabled={disableForcedCheck}
            disableGutters
            onClick={onClickForcedCheck}
          >
            <Icon display={!isDefaultChecked} />

            <ListItemText
              className={classes.itemText}
              primary={
                <Text
                  description={rest.descriptionForcedCheck}
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
