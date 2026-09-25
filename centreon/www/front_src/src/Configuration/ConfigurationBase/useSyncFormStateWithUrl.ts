import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect, useRef } from 'react';
import { useSearchParams } from 'react-router';

import { formStateAtom } from './atoms';

interface Props {
  hasFormAccess: boolean;
}

// The form is deep-linkable through `?mode=add|edit` and `?id=`. The sync lives
// on the page rather than in the form itself: the panel is mounted only while
// it is open, so it cannot be what reacts to the URL that opens it.
const useSyncFormStateWithUrl = ({ hasFormAccess }: Props): void => {
  const [searchParams] = useSearchParams();

  const [formState, setFormState] = useAtom(formStateAtom);

  // Read at call time, never depended on: the effect must answer to the URL
  // alone. Waking on the state it writes would race react-router, which
  // publishes the new URL a commit later.
  const formStateRef = useRef(formState);
  formStateRef.current = formState;

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (!mode || !hasFormAccess) {
      return;
    }

    const urlId = id ? Number(id) : null;

    // Opening the form writes the URL, which fires this effect right back:
    // re-setting the state here would drop the row the listing handed over.
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
