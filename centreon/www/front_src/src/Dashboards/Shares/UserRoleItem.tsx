import { ChangeEvent, ReactNode } from 'react';

import { head } from 'ramda';

import { Box, SelectChangeEvent } from '@mui/material';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import RotateRightIcon from '@mui/icons-material/RotateRight';

import { IconButton, List } from '@centreon/ui/components';
import { SelectField, useMemoComponent } from '@centreon/ui';

import { DashboardRole } from '../models';

const options = [
  {
    id: DashboardRole.editor,
    name: DashboardRole.editor
  },
  {
    id: DashboardRole.viewer,
    name: DashboardRole.viewer
  }
];

interface Props {
  change: ((e: ChangeEvent<HTMLInputElement>) => void) &
    ((event: SelectChangeEvent<unknown>, child: ReactNode) => void);
  elementRef?: (props) => void;
  email: string | null;
  fullname: string | null;
  id: number;
  isRemoved: boolean;
  name: string;
  role: DashboardRole;
  toggle: () => void;
}

const UserRoleItem = ({
  role,
  change,
  toggle,
  id,
  elementRef,
  fullname,
  email,
  name,
  isRemoved
}: Props): JSX.Element => {
  return useMemoComponent({
    Component: (
      <List.Item
        action={
          <Box sx={{ columnGap: 2, display: 'flex' }}>
            <SelectField
              dataTestId="change_role"
              disabled={isRemoved}
              options={options}
              selectedOptionId={role}
              sx={{ width: 85 }}
              onChange={change}
            />
            {isRemoved ? (
              <IconButton
                data-testid="add_user"
                icon={<RotateRightIcon />}
                onClick={toggle}
              />
            ) : (
              <IconButton
                data-testid="remove_user"
                icon={<DeleteOutlineIcon />}
                onClick={toggle}
              />
            )}
          </Box>
        }
        key={id}
        ref={elementRef}
      >
        <List.Item.Avatar>{head(fullname || name)}</List.Item.Avatar>
        <List.Item.Text
          primaryText={fullname || name}
          secondaryText={email || undefined}
        />
      </List.Item>
    ),
    memoProps: [id, role, email, fullname, isRemoved]
  });
};

export default UserRoleItem;
