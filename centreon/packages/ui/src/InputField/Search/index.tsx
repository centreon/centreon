import IconSearch from '@mui/icons-material/Search';

import TextField, { Props as TextFieldProps } from '../Text';

type Props = Omit<TextFieldProps, 'StartAdornment'>;

const SearchAdornment = (): JSX.Element => <IconSearch />;

const SearchField = (props: Props): JSX.Element => (
  <TextField StartAdornment={SearchAdornment} {...props} />
);

export default SearchField;
