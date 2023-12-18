import { useSetAtom } from 'jotai';

import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import RotateLeftIcon from '@mui/icons-material/RotateLeft';

import { IconButton } from '../../..';
import { removeAccessRightDerivedAtom } from '../atoms';

interface Props {
  index: number;
  isRemoved: boolean;
}

const RemoveAccessRight = ({ index, isRemoved }: Props): JSX.Element => {
  const removeAccessRight = useSetAtom(removeAccessRightDerivedAtom);

  const remove = (): void => removeAccessRight({ index });
  const recover = (): void => removeAccessRight({ index, recover: true });

  const icon = isRemoved ? (
    <RotateLeftIcon fontSize="small" />
  ) : (
    <DeleteOutlineIcon color="error" fontSize="small" />
  );

  return (
    <IconButton
      icon={icon}
      size="small"
      onClick={isRemoved ? recover : remove}
    />
  );
};

export default RemoveAccessRight;
