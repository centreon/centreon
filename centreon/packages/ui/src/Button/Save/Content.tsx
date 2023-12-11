import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';
import { Typography, TypographyProps } from '@mui/material';

interface Props {
  labelLoading: string;
  labelSave: string;
  labelSucceeded: string;
  loading: boolean;
  succeeded: boolean;
}

interface WrapperLabelProps {
  label: string;
}

const WrapperLabel = ({
  label,
  ...rest
}: WrapperLabelProps & TypographyProps): JSX.Element => {
  return <Typography {...rest}>{label}</Typography>;
};

const Content = ({
  succeeded,
  labelSucceeded,
  labelSave,
  loading,
  labelLoading,
  ...rest
}: Props & TypographyProps): JSX.Element | string | null => {
  if (loading) {
    return <WrapperLabel label={labelLoading} {...rest} />;
  }

  if (succeeded) {
    return labelSucceeded ? (
      <WrapperLabel label={labelSucceeded} {...rest} />
    ) : (
      <CheckIcon />
    );
  }

  return labelSave ? (
    <WrapperLabel label={labelSave} {...rest} />
  ) : (
    <SaveIcon />
  );
};

export default Content;
