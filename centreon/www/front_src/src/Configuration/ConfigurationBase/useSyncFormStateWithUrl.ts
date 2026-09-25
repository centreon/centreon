import { useSetAtom } from 'jotai';
import { useEffect } from 'react';
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

  const setFormState = useSetAtom(formStateAtom);

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (!mode || !hasFormAccess) {
      return;
    }

    setFormState({
      id: id ? Number(id) : null,
      isOpen: true,
      mode: mode as 'add' | 'edit'
    });
  }, [searchParams, setFormState, hasFormAccess]);
};

export default useSyncFormStateWithUrl;
