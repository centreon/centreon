import PersonOffOutlinedIcon from '@mui/icons-material/PersonOffOutlined';
import SettingsOutlinedIcon from '@mui/icons-material/SettingsOutlined';
import { Typography } from '@mui/material';

import { Avatar } from '@centreon/ui/components';

import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import type { Actor } from './models';
import { labelSystem, labelUnknown } from './translatedLabels';

const AuthorLine = ({
  avatar,
  name
}: {
  avatar: ReactNode;
  name: string;
}): JSX.Element => (
  <span className="flex min-w-0 items-center gap-2">
    {avatar}
    <Typography className="truncate" component="span" variant="body2">
      {name}
    </Typography>
  </span>
);

const iconAvatarClassName =
  'h-6 w-6 bg-background-panel text-text-secondary [&_svg]:text-base';

interface Props {
  actor?: Actor | null;
}

// People get an avatar with their initials, changes made by the platform get
// a gear, so human and automated changes are told apart at a glance
const Author = ({ actor }: Props): JSX.Element => {
  const { t } = useTranslation();

  if (!actor) {
    return (
      <AuthorLine
        avatar={
          <Avatar className={iconAvatarClassName} compact>
            <SettingsOutlinedIcon />
          </Avatar>
        }
        name={t(labelSystem)}
      />
    );
  }

  if (!actor.name) {
    return (
      <AuthorLine
        avatar={
          <Avatar className={iconAvatarClassName} compact>
            <PersonOffOutlinedIcon />
          </Avatar>
        }
        name={t(labelUnknown)}
      />
    );
  }

  return (
    <AuthorLine
      avatar={
        <Avatar className="h-6 w-6 text-xs" compact>
          {actor.name.substring(0, 2).toUpperCase()}
        </Avatar>
      }
      name={actor.name}
    />
  );
};

export default Author;
