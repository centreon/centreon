import { useAtom } from 'jotai';
import { equals } from 'ramda';
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

  const [formState, setFormState] = useAtom(formStateAtom);

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (!mode || !hasFormAccess) {
      return;
    }

    const urlId = id ? Number(id) : null;

    // Opening the form writes the URL, which fires this effect right back:
    // re-setting the state here would drop the row the listing handed over.
    const describesTheOpenForm =
      formState.isOpen &&
      equals(formState.mode, mode) &&
      equals(formState.id, urlId);

    if (describesTheOpenForm) {
      return;
    }

    setFormState({
      id: urlId,
      isOpen: true,
      mode: mode as 'add' | 'edit',
      resource: null
    });
  }, [searchParams, setFormState, hasFormAccess, formState]);
};

export default useSyncFormStateWithUrl;
