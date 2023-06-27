import { ChangeEvent, ReactNode } from 'react';

import { head } from 'ramda';

import { Box, SelectChangeEvent } from '@mui/material';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';

import { IconButton, List } from '@centreon/ui/components';
import { SelectField, useMemoComponent } from '@centreon/ui';

import { DashboardRole } from '../api/models';

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
  name: string;
  remove: () => void;
  role: DashboardRole;
}

const UserRoleItem = ({
  role,
  change,
  remove,
  id,
  elementRef,
  fullname,
  email,
  name
}: Props): JSX.Element => {
  return useMemoComponent({
    Component: (
      <List.Item
        action={
          <Box sx={{ columnGap: 2, display: 'flex' }}>
            <SelectField
              dataTestId="change_role"
              options={options}
              selectedOptionId={role}
              sx={{ width: 85 }}
              onChange={change}
            />
            <IconButton
              data-testid="remove_user"
              icon={<DeleteOutlineIcon />}
              onClick={remove}
            />
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
    memoProps: [id, role, email, fullname]
  });
};

export default UserRoleItem;
