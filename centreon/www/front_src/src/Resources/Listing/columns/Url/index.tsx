import { Avatar, Tooltip } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { isEmpty, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import IconColumn from '../IconColumn';

const useStyles = makeStyles()((theme) => ({
  avatar: {
    backgroundColor: theme.palette.primary.main,
    fontSize: theme.typography.body2.fontSize,
    height: theme.spacing(2),
    width: theme.spacing(2)
  },
  button: {
    padding: 0
  }
}));

interface Props {
  avatarTitle?: string;
  endpoint?: string;
  icon: JSX.Element;
  title?: string;
}

const UrlColumn = ({
  endpoint,
  title,
  icon,
  avatarTitle
}: Props): JSX.Element | null => {
  const { classes } = useStyles();

  const isEndpointEmpty = isNil(endpoint) || isEmpty(endpoint);
  const isTitleEmpty = isNil(title) || isEmpty(title);

  if (isEndpointEmpty && isTitleEmpty) {
    return null;
  }

  if (isEndpointEmpty) {
    return (
      <IconColumn>
        <Tooltip className={classes.avatar} title={title as string}>
          <Avatar>{avatarTitle}</Avatar>
        </Tooltip>
      </IconColumn>
    );
  }

  return (
    <IconColumn>
      <a
        href={endpoint}
        onClick={(e): void => {
          e.stopPropagation();
        }}
      >
        <IconButton
          ariaLabel={title}
          className={classes.button}
          data-testid={title || endpoint}
          onClick={(): null => {
            return null;
          }}
          size="large"
          title={title || endpoint}
        >
          {icon}
        </IconButton>
      </a>
    </IconColumn>
  );
};

export default UrlColumn;
