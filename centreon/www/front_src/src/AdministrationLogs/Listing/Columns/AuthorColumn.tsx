import PersonOffOutlinedIcon from '@mui/icons-material/PersonOffOutlined';
import SettingsOutlinedIcon from '@mui/icons-material/SettingsOutlined';
import { Typography } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';
import { Avatar } from '@centreon/ui/components';

import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import type { ActivityLog } from '../../models';
import { labelSystem, labelUnknown } from '../../translatedLabels';
import { useAddFilter } from '../../useAddFilter';
import FilterableValue from './FilterableValue';

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

// People get an avatar with their initials, changes made by the platform get
// a gear, so human and automated changes are told apart at a glance
const AuthorColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const addFilter = useAddFilter();

  const { actor } = row as unknown as ActivityLog;

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

  const { id, name } = actor;

  return (
    <FilterableValue
      label={name}
      onFilter={() => addFilter('actors', { id, name })}
    >
      <AuthorLine
        avatar={
          <Avatar className="h-6 w-6 text-xs" compact>
            {name.substring(0, 2).toUpperCase()}
          </Avatar>
        }
        name={name}
      />
    </FilterableValue>
  );
};

export default AuthorColumn;
