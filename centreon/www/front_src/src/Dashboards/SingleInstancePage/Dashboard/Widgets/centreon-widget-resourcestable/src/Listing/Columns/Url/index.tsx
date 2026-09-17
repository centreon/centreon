import { Avatar, Tooltip } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { isEmpty, isNil } from 'ramda';

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
  const isEndpointEmpty = isNil(endpoint) || isEmpty(endpoint);
  const isTitleEmpty = isNil(title) || isEmpty(title);

  if (isEndpointEmpty && isTitleEmpty) {
    return null;
  }

  if (isEndpointEmpty) {
    return (
      <div className="flex w-full justify-start">
        <Tooltip title={title as string}>
          <Avatar
            className="h-4 w-4 bg-primary-main text-xs"
            data-testid={title}
          >
            {avatarTitle}
          </Avatar>
        </Tooltip>
      </div>
    );
  }

  return (
    <div className="flex w-full justify-start">
      <a
        href={endpoint}
        onClick={(e): void => {
          e.stopPropagation();
        }}
      >
        <IconButton
          ariaLabel={title}
          className="p-0"
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
    </div>
  );
};

export default UrlColumn;
