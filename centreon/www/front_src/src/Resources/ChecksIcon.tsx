import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import SyncDisabledIcon from '@mui/icons-material/SyncDisabled';
import SyncProblemIcon from '@mui/icons-material/SyncProblem';
import { Tooltip } from '@mui/material';

import { Resource } from './models';
import {
  labelChecksDisabled,
  labelOnlyPassiveChecksEnabled
} from './translatedLabels';

interface IconProps {
  Component: (props) => JSX.Element;
  title: string;
}

const Icon = ({ Component, title }: IconProps): JSX.Element => {
  const { t } = useTranslation();
  const translatedTitle = t(title);

  return (
    <Tooltip title={translatedTitle}>
      <Component color="primary" fontSize="small" />
    </Tooltip>
  );
};

type Props = Pick<
  Resource,
  'has_active_checks_enabled' | 'has_passive_checks_enabled'
>;

const ChecksIcon = ({
  has_active_checks_enabled,
  has_passive_checks_enabled
}: Props): JSX.Element | null => {
  if (
    equals(has_passive_checks_enabled, false) &&
    equals(has_active_checks_enabled, false)
  ) {
    return <Icon Component={SyncDisabledIcon} title={labelChecksDisabled} />;
  }

  if (equals(has_active_checks_enabled, false)) {
    return (
      <Icon Component={SyncProblemIcon} title={labelOnlyPassiveChecksEnabled} />
    );
  }

  return null;
};

export default ChecksIcon;
