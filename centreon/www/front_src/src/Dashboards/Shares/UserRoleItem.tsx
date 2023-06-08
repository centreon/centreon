import { ChangeEvent, ReactNode } from 'react';

import { head } from 'ramda';

import { Box, SelectChangeEvent } from '@mui/material';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';

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
  email?: string;
  fullname: string;
  id: number;
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
  email
}: Props): JSX.Element => {
  return useMemoComponent({
    Component: (
      <List.Item
        action={
          <Box sx={{ columnGap: 2, display: 'flex' }}>
            <SelectField
              dataTestId="role"
              options={options}
              selectedOptionId={role}
              sx={{ width: 85 }}
              onChange={change}
            />
            <IconButton icon={<DeleteOutlineIcon />} onClick={remove} />
          </Box>
        }
        key={id}
        ref={elementRef}
      >
        <List.Avatar>{head(fullname)}</List.Avatar>
        <List.ItemText primaryText={fullname} secondaryText={email} />
      </List.Item>
    ),
    memoProps: [id, role, email, fullname]
  });
};

export default UserRoleItem;
