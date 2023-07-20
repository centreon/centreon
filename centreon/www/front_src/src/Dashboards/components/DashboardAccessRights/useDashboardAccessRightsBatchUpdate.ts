import { ContactAccessRightStateResource } from '@centreon/ui/components';

import { NamedEntity } from '../../api/models';
import { useCreateAccessRightsContact } from '../../api/useCreateAccessRightsContact';
import { useUpdateAccessRightsContact } from '../../api/useUpdateAccessRightsContact';
import { useDeleteAccessRightsContact } from '../../api/useDeleteAccessRightsContact';
import { useCreateAccessRightsContactGroup } from '../../api/useCreateAccessRightsContactGroup';
import { useUpdateAccessRightsContactGroup } from '../../api/useUpdateAccessRightsContactGroup';
import { useDeleteAccessRightsContactGroup } from '../../api/useDeleteAccessRightsContactGroup';

import {
  transformContactAccessRightToCreateDto,
  transformContactAccessRightToDeleteDto,
  transformContactAccessRightToUpdateDto
} from './useDashboardAccessRights.utils';

type BatchUpdateAccessRightsArgs = {
  entityId: NamedEntity['id'];
  values: Array<ContactAccessRightStateResource>;
};

type UseDashboardAccessRightsBatchUpdate = {
  batchUpdateAccessRights: (args: BatchUpdateAccessRightsArgs) => Promise<void>;
};

const useDashboardAccessRightsBatchUpdate =
  (): UseDashboardAccessRightsBatchUpdate => {
    const { mutate: createAccessRightsContact } =
      useCreateAccessRightsContact();
    const { mutate: updateAccessRightsContact } =
      useUpdateAccessRightsContact();
    const { mutate: deleteAccessRightsContact } =
      useDeleteAccessRightsContact();
    const { mutate: createAccessRightsContactGroup } =
      useCreateAccessRightsContactGroup();
    const { mutate: updateAccessRightsContactGroup } =
      useUpdateAccessRightsContactGroup();
    const { mutate: deleteAccessRightsContactGroup } =
      useDeleteAccessRightsContactGroup();

    const addedAccessRightsBatch = ({
      values,
      entityId
    }: BatchUpdateAccessRightsArgs): Array<Promise<unknown>> =>
      values
        .filter((value) => value.state === 'added')
        .map((value) => {
          const dto = transformContactAccessRightToCreateDto(
            value.contactAccessRight,
            entityId
          );

          return value.contactAccessRight.contact?.type === 'contact'
            ? createAccessRightsContact(dto)
            : createAccessRightsContactGroup(dto);
        });

    const updatedAccessRightsBatch = ({
      values,
      entityId
    }: BatchUpdateAccessRightsArgs): Array<Promise<unknown>> =>
      values
        .filter((value) => value.state === 'updated')
        .map((value) => {
          const dto = transformContactAccessRightToUpdateDto(
            { ...value.contactAccessRight },
            entityId
          );

          return value.contactAccessRight.contact?.type === 'contact'
            ? updateAccessRightsContact(dto)
            : updateAccessRightsContactGroup(dto);
        });

    const removedAccessRightsBatch = ({
      values,
      entityId
    }: BatchUpdateAccessRightsArgs): Array<Promise<unknown>> =>
      values
        .filter((value) => value.state === 'removed')
        .map((value) => {
          const dto = transformContactAccessRightToDeleteDto(
            value.contactAccessRight,
            entityId
          );

          return value.contactAccessRight.contact?.type === 'contact'
            ? deleteAccessRightsContact(dto)
            : deleteAccessRightsContactGroup(dto);
        });

    const batchUpdateAccessRights = async ({
      values,
      entityId
    }: BatchUpdateAccessRightsArgs): Promise<void> => {
      const promises = [
        ...addedAccessRightsBatch({ entityId, values }),
        ...updatedAccessRightsBatch({ entityId, values }),
        ...removedAccessRightsBatch({ entityId, values })
      ];

      await Promise.all(promises);
    };

    return {
      batchUpdateAccessRights
    };
  };

export { useDashboardAccessRightsBatchUpdate };
