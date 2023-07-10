import { ContactAccessRightResource } from '@centreon/ui/components';

import {
  DashboardAccessRightsContact,
  DashboardAccessRightsContactGroup
} from '../../api/models';

export const transformAccessRightContactOrContactGroup = (
  accessRight: DashboardAccessRightsContact | DashboardAccessRightsContactGroup
): ContactAccessRightResource => ({
  contact: {
    id: accessRight.id,
    name: accessRight.name,
    type: accessRight.type,
    ...(accessRight.type === 'contact' ? { email: accessRight.email } : {})
  },
  role: accessRight.role
});
