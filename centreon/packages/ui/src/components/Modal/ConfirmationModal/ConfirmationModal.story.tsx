import { Button } from '@mui/material';

import { atom, createStore, Provider, useSetAtom } from 'jotai';
import { useState } from 'react';

import { ConfirmationModal } from './ConfirmationModal';

interface Props {
  disabled?: boolean;
  dynamicLabels?: boolean;
  hasCloseButton?: boolean;
  isDanger?: boolean;
}

const testAtom = atom<string | null>(null);

const labels = {
  cancel: 'Cancel',
  confirm: 'Confirm',
  description: 'Description',
  title: 'Title'
};

const dynamicLabels = {
  ...labels,
  description: (data: string) => `Description ${data}`,
  title: (data: string) => `Hello ${data}`
};

const Opener = (props: Props): JSX.Element => {
  const open = useSetAtom(testAtom);
  const [calls, setCalls] = useState<Array<string>>([]);
  const record = (name: string) => (data: string | null) =>
    setCalls((current) => [...current, `${name} ${data}`]);

  return (
    <>
      <Button onClick={() => open('John')}>Click to open modal</Button>
      <ConfirmationModal<string>
        {...props}
        atom={testAtom}
        labels={(props.dynamicLabels ? dynamicLabels : labels) as never}
        onCancel={record('cancel')}
        onConfirm={record('confirm')}
      />
      <output data-testid="calls">{calls.join(', ')}</output>
    </>
  );
};

// Records the calls of the callbacks in `calls`.
export const Default = (props: Props): JSX.Element => (
  <Provider store={createStore()}>
    <Opener {...props} />
  </Provider>
);
