import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import type { ReactElement } from 'react';
import { useSearchParams } from 'react-router';

import {
  formStateAtom,
  formStateOnDiscardAtom,
  isCloseConfirmationDialogOpenAtom
} from '../../ConfigurationBase/atoms';
import type { FormState } from '../../ConfigurationBase/models';
import OrderedTemplates from '../../ResourceForm/OrderedTemplates';
import {
  getParentTemplatesEndpoint,
  hostTemplatesBaseEndpoint
} from '../api/endpoints';

// A parent opens in this panel; a dirty form gets the close confirmation
// first, and a discard there opens the parent.
const ParentTemplates = (props: InputPropsWithoutGroup): ReactElement => {
  const { dirty } = useFormikContext();
  const [, setSearchParams] = useSearchParams();
  const [{ id, mode }, setFormState] = useAtom(formStateAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const setFormStateOnDiscard = useSetAtom(formStateOnDiscardAtom);

  const editedTemplateId = equals(mode, 'edit') ? id : null;

  const openParent = (parentId: number): void => {
    const parent: FormState = {
      id: parentId,
      isOpen: true,
      mode: 'edit',
      resource: null
    };

    if (dirty) {
      setFormStateOnDiscard(parent);
      setIsCloseConfirmationDialogOpen(true);

      return;
    }

    setSearchParams({ id: String(parentId), mode: 'edit' });
    setFormState(parent);
  };

  return (
    <OrderedTemplates
      {...props}
      config={{
        baseEndpoint: hostTemplatesBaseEndpoint,
        getEndpoint: getParentTemplatesEndpoint(editedTemplateId),
        onEdit: openParent,
        // Per edited template: the choices differ from one to the next.
        queryKey: `host-template-form-templates-${editedTemplateId ?? 'new'}`
      }}
    />
  );
};

export default ParentTemplates;
