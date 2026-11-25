import { EditNoteOutlined as MassChangeIcon } from '@mui/icons-material';

import { useSetAtom } from 'jotai';
import { prop, uniqBy } from 'ramda';
import { useMemo } from 'react';

import type { MassiveAction } from '../models';
import { formStateAtom } from './atoms';
import { labelMassChange } from './translatedLabels';

const useMassChangeAction = (): MassiveAction => {
  const setFormState = useSetAtom(formStateAtom);

  return useMemo(
    () => ({
      dataTestId: 'mass-change',
      Icon: MassChangeIcon,
      label: labelMassChange,
      onClick: (rows) =>
        setFormState({
          id: null,
          isOpen: true,
          mode: 'massChange',
          resource: null,
          // A resource listed on several rows (a service on several hosts) is
          // one resource: one write for it.
          selection: uniqBy(prop('id'), rows).map(({ id, name }) => ({
            id: id as number,
            name: name as string
          }))
        })
    }),
    [setFormState]
  );
};

export default useMassChangeAction;
