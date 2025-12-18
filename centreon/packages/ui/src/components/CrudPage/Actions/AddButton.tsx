import { Add } from '@mui/icons-material';

import { useSetAtom } from 'jotai';
import { useCallback } from 'react';

import { Button } from '../../Button';
import { openFormModalAtom } from '../atoms';

interface Props {
  label: string;
}

const AddButton = ({ label }: Props): JSX.Element => {
  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const add = useCallback(() => setOpenFormModal('add'), [setOpenFormModal]);

  return (
    <Button icon={<Add />} iconVariant="start" onClick={add} size="small">
      {label}
    </Button>
  );
};

export default AddButton;
