import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useRef } from 'react';
import { useSearchParams } from 'react-router';

import { formStateAtom } from './atoms';

interface Props {
  hasFormAccess: boolean;
}

// Deep links (`?mode=`, `?id=`) are handled here rather than in the form: the
// panel is mounted only while open, so it cannot react to the URL opening it.
const useSyncFormStateWithUrl = ({ hasFormAccess }: Props): void => {
  const [searchParams] = useSearchParams();

  const [formState, setFormState] = useAtom(formStateAtom);

  // Read at call time, never depended on: waking on the state this effect
  // writes races react-router, which publishes the URL a commit later.
  const formStateRef = useRef(formState);
  formStateRef.current = formState;

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (!mode || !hasFormAccess) {
      return;
    }

    const urlId = id ? Number(id) : null;

    const currentFormState = formStateRef.current;

    const describesTheOpenForm =
      currentFormState.isOpen &&
      equals(currentFormState.mode, mode) &&
      equals(currentFormState.id, urlId);

    if (describesTheOpenForm) {
      return;
    }

    setFormState({
      id: urlId,
      isOpen: true,
      mode: mode as 'add' | 'edit',
      resource: null
    });
  }, [searchParams, setFormState, hasFormAccess]);
};

export default useSyncFormStateWithUrl;
