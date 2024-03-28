import IconSearch from '@mui/icons-material/Search';

import TextField, { TextProps } from '../Text';

type Props = Omit<TextProps, 'StartAdornment'>;

const SearchAdornment = (): JSX.Element => <IconSearch />;

const SearchField = (props: Props): JSX.Element => (
  <TextField StartAdornment={SearchAdornment} {...props} />
);

export default SearchField;
