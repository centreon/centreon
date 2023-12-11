import Typography, { TypographyTypeMap } from '@mui/material/Typography';

interface Props {
  data: string;
  variant?: TypographyTypeMap['props']['variant'];
}

const Information = ({ data, variant = 'body2' }: Props): JSX.Element => {
  return <Typography variant={variant}>{data}</Typography>;
};

export default Information;
