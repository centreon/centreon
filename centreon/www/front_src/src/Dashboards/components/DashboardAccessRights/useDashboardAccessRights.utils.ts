import { ContactAccessRightResource } from '@centreon/ui/components';

import {
  CreateAccessRightDto,
  DashboardAccessRightsContact,
  DashboardAccessRightsContactGroup,
  DashboardRole,
  DeleteAccessRightDto,
  NamedEntity,
  UpdateAccessRightDto
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

export const transformContactAccessRightToCreateDto = (
  accessRight: ContactAccessRightResource,
  dashboardId: NamedEntity['id']
): CreateAccessRightDto => ({
  dashboardId,
  id: accessRight.contact?.id as number,
  role: accessRight.role as DashboardRole
});

export const transformContactAccessRightToUpdateDto = (
  accessRight: ContactAccessRightResource,
  dashboardId: NamedEntity['id']
): UpdateAccessRightDto => ({
  dashboardId,
  id: accessRight.contact?.id as number,
  role: accessRight.role as DashboardRole
});

export const transformContactAccessRightToDeleteDto = (
  accessRight: ContactAccessRightResource,
  dashboardId: NamedEntity['id']
): DeleteAccessRightDto => ({
  dashboardId,
  id: accessRight.contact?.id as number
});
