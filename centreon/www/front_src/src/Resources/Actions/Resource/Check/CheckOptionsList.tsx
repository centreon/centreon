import {
  MutableRefObject,
  RefObject,
  useEffect,
  useRef,
  useState
} from 'react';

import { makeStyles } from 'tss-react/mui';

import IconCheck from '@mui/icons-material/CheckOutlined';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Popper from '@mui/material/Popper';

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
    zIndex: 900
  }
}));

interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

interface Props {
  anchorEl?: HTMLElement | null;
  buttonGroupReference?: MutableRefObject<HTMLDivElement>;
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
  disabled,
  buttonGroupReference
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { disableForcedCheck, disableCheck } = disabled;
  const listReference = useRef<HTMLDivElement>();
  const [skiddingPopper, setSkiddingPopper] = useState(0);
  const widthArrow = 28;

  const handlePositionPopper = (): void => {
    const skidding =
      buttonGroupReference?.current?.getBoundingClientRect()?.width ??
      widthArrow - widthArrow;

    setSkiddingPopper(skidding - widthArrow);
  };

  useEffect(() => {
    handlePositionPopper();
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }
    handlePositionPopper();
  }, [open]);

  return (
    <Popper
      anchorEl={anchorEl}
      className={classes.popover}
      modifiers={[
        {
          name: 'offset',
          options: {
            offset: [-skiddingPopper, 4]
          }
        }
      ]}
      open={open}
      placement="bottom-start"
      ref={listReference as RefObject<HTMLDivElement>}
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
    </Popper>
  );
};

export default CheckOptionsList;
