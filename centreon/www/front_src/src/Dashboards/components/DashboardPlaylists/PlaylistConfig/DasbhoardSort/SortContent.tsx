import { Typography } from '@mui/material';

interface Props {
  name: string;
}

const SortContent = ({ name }: Props): JSX.Element => (
  <Typography>{name}</Typography>
);

export default SortContent;
