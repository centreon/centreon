import { ReactElement } from 'react';

import { renderHook } from '@testing-library/react-hooks';
import { act } from '@testing-library/react';

import {
  AccessRightsFormProvider,
  AccessRightsFormProviderProps,
  useAccessRightsForm
} from './useAccessRightsForm';
import {
  contactAccessRightsMock,
  contactsMock,
  rolesMock
} from './__fixtures__/contactAccessRight.mock';

const contextWrapper = (
  contextProps: AccessRightsFormProviderProps
): ReactElement => <AccessRightsFormProvider {...contextProps} />;

describe('useAccessRightsForm', () => {
  const baseContextProps: AccessRightsFormProviderProps = {
    children: null,
    options: {
      contacts: contactsMock(25),
      roles: rolesMock()
    }
  };

  const initialValues: AccessRightsFormProviderProps['initialValues'] =
    contactAccessRightsMock(30);

  it('should render the hook', () => {
    const contextProps = { ...baseContextProps };

    const { result } = renderHook(() => useAccessRightsForm(), {
      initialProps: contextProps,
      wrapper: contextWrapper
    });

    expect(result.current).toBeDefined();
  });

  describe('Provider', () => {
    it('should persist the `options`', () => {
      const contextProps = { ...baseContextProps };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      expect(result.current.options).toBeDefined();
      expect(result.current.options).toEqual(contextProps.options);
    });

    it('should persist the `initialValues` as `contactAccessRights`', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      expect(result.current.contactAccessRights).toBeDefined();
      expect(result.current.contactAccessRights).toHaveLength(
        initialValues.length
      );
      initialValues.forEach((initialValue) => {
        expect(
          result.current.contactAccessRights.find(
            ({ contactAccessRight: { contact } }) =>
              contact?.id === initialValue.contact?.id
          )
        ).toBeDefined();
      });
    });
  });

  describe('addContactAccessRight', () => {
    it('should add a contact access right', () => {
      const contextProps = { ...baseContextProps };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const contactAccessRight = contactAccessRightsMock(1)[0];
      act(() => result.current.addContactAccessRight(contactAccessRight));

      expect(result.current.contactAccessRights).toHaveLength(1);
      expect(
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === contactAccessRight.contact?.id
        )
      ).toBeDefined();
    });
    it('should not add a contact access right if it already exists', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      act(() => result.current.addContactAccessRight(contactAccessRight));

      expect(result.current.contactAccessRights).toHaveLength(
        initialValues.length
      );
    });
  });

  describe('removeContactAccessRight', () => {
    it('should remove a contact access right of a pre-existing', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      act(() => result.current.removeContactAccessRight(contactAccessRight));

      expect(result.current.contactAccessRights).toHaveLength(
        initialValues.length
      );

      expect(
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact }, state }) =>
            contact?.id === contactAccessRight.contact?.id &&
            state === 'removed'
        )
      ).toBeDefined();
    });
    it('should restore a contact access right if it was pre-existing', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      act(() => result.current.removeContactAccessRight(contactAccessRight));
      act(() =>
        result.current.removeContactAccessRight(contactAccessRight, true)
      );

      expect(
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact }, state }) =>
            contact?.id === contactAccessRight.contact?.id &&
            state === 'unchanged'
        )
      ).toBeDefined();
    });
    it('should completely remove a contact access right if it was newly added', () => {
      const contextProps = { ...baseContextProps };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const contactAccessRight = contactAccessRightsMock(1)[0];
      act(() => result.current.addContactAccessRight(contactAccessRight));
      act(() => result.current.removeContactAccessRight(contactAccessRight));

      expect(result.current.contactAccessRights).toHaveLength(0);
    });
    it('should restore a contact access right to it previous state', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );
      act(() =>
        result.current.removeContactAccessRight(updatedContactAccessRight)
      );
      act(() =>
        result.current.removeContactAccessRight(updatedContactAccessRight, true)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();
      expect(updatedContactAccessRightState?.state).toEqual('updated');
    });
  });

  describe('updateContactAccessRight', () => {
    it('should update a contact access right', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();

      expect(updatedContactAccessRightState?.state).toEqual('updated');

      expect(
        updatedContactAccessRightState?.contactAccessRight.role ===
          updatedContactAccessRight.role
      ).toBeTruthy();
    });

    it('should update a contact access right to its previous state', () => {
      const contextProps = {
        ...baseContextProps,
        initialValues
      };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      updatedContactAccessRight.role =
        updatedContactAccessRight.role === rolesMock()[0].role
          ? rolesMock()[1].role
          : rolesMock()[0].role;

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();
      expect(updatedContactAccessRightState?.state).toEqual('unchanged');
    });

    it('should update a contact access right to `unchanged` state if it is updated to its original value', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );
      act(() =>
        result.current.removeContactAccessRight(updatedContactAccessRight)
      );
      act(() =>
        result.current.removeContactAccessRight(updatedContactAccessRight, true)
      );

      updatedContactAccessRight.role =
        updatedContactAccessRight.role === rolesMock()[0].role
          ? rolesMock()[1].role
          : rolesMock()[0].role;

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();
      expect(updatedContactAccessRightState?.state).toEqual('unchanged');
    });

    it('should update a contact access right to `updated` state if it is updated subsequently', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );
      act(() => result.current.updateContactAccessRight(contactAccessRight));
      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();
      expect(updatedContactAccessRightState?.state).toEqual('updated');
    });

    it('should keep a new contact access right in `added` state if it is updated', () => {
      const contextProps = { ...baseContextProps };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const contactAccessRight = contactAccessRightsMock(1)[0];
      act(() => result.current.addContactAccessRight(contactAccessRight));

      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      const updatedContactAccessRightState =
        result.current.contactAccessRights.find(
          ({ contactAccessRight: { contact } }) =>
            contact?.id === updatedContactAccessRight.contact?.id
        );

      expect(updatedContactAccessRightState).toBeDefined();
      expect(updatedContactAccessRightState?.state).toEqual('added');
    });
  });

  describe('onSubmit', () => {
    it('should call the `onSubmit` callback with the correct payload', () => {
      const contextProps = { ...baseContextProps, initialValues };
      const onSubmit = jest.fn();

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: { ...contextProps, onSubmit },
        wrapper: contextWrapper
      });

      act(() => result.current.submit());

      expect(onSubmit).toHaveBeenCalledWith(result.current.contactAccessRights);
    });
  });

  describe('state', () => {
    it('should have `isDirty` flag set on changes', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      const updatedContactAccessRight = {
        ...contactAccessRight,
        role:
          contactAccessRight.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      expect(result.current.isDirty).toBeFalsy();

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      expect(result.current.isDirty).toBeTruthy();
    });

    it('should have `isDirty` flag cleared on reverted changes', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      const { contactAccessRight } = result.current.contactAccessRights[0];
      act(() => result.current.removeContactAccessRight(contactAccessRight));
      act(() =>
        result.current.removeContactAccessRight(contactAccessRight, true)
      );

      expect(result.current.isDirty).toBeFalsy();
    });

    it('should have stats updated on changes', () => {
      const contextProps = { ...baseContextProps, initialValues };

      const { result } = renderHook(() => useAccessRightsForm(), {
        initialProps: contextProps,
        wrapper: contextWrapper
      });

      expect(result.current.stats).toEqual({
        added: 0,
        removed: 0,
        updated: 0
      });

      // update contact access right
      const { contactAccessRight: contactAccessRightToUpdate } =
        result.current.contactAccessRights[1];
      const updatedContactAccessRight = {
        ...contactAccessRightToUpdate,
        role:
          contactAccessRightToUpdate.role === rolesMock()[0].role
            ? rolesMock()[1].role
            : rolesMock()[0].role
      };

      act(() =>
        result.current.updateContactAccessRight(updatedContactAccessRight)
      );

      expect(result.current.stats).toEqual({
        added: 0,
        removed: 0,
        updated: 1
      });

      // remove contact access right
      const { contactAccessRight: contactAccessRightToRemove } =
        result.current.contactAccessRights[2];

      act(() =>
        result.current.removeContactAccessRight(contactAccessRightToRemove)
      );

      expect(result.current.stats).toEqual({
        added: 0,
        removed: 1,
        updated: 1
      });

      // add contact access right
      const contactAccessRight = contactAccessRightsMock(1)[0];
      act(() => result.current.addContactAccessRight(contactAccessRight));

      expect(result.current.stats).toEqual({
        added: 1,
        removed: 1,
        updated: 1
      });
    });
  });
});
