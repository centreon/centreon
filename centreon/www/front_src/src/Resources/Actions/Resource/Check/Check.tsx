import { useTranslation } from 'react-i18next';

import ResourceActionButton from '../ResourceActionButton';

import ListCheckOptions from './ListCheckOptions';

interface ClickList {
  onClickCheck: () => void;
  onClickForcedCheck: () => void;
}
interface Disabled {
  disableCheck: boolean;
  disableForcedCheck: boolean;
}

interface Props {
  anchorEl: HTMLElement | null;
  disabledButton: boolean;
  disabledList: Disabled;
  icon: JSX.Element;
  isActionPermitted: boolean;
  isDefaultChecked: boolean;
  labelButton: string;
  onClick: (event) => void;
  onClickList: ClickList;
  onClose: () => void;
  secondaryIcon: JSX.Element;
  testId: string;
}

const Check = ({
  disabledButton,
  disabledList,
  labelButton,
  isActionPermitted,
  testId,
  onClickList,
  onClick,
  onClose,
  icon,
  anchorEl,
  secondaryIcon,
  isDefaultChecked
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { onClickCheck, onClickForcedCheck } = onClickList;

  return (
    <>
      <ResourceActionButton
        disabled={disabledButton}
        icon={icon}
        label={t(labelButton)}
        permitted={isActionPermitted}
        secondaryIcon={secondaryIcon}
        testId={testId}
        onClick={onClick}
      />
      <ListCheckOptions
        anchorEl={anchorEl}
        disabled={disabledList}
        isDefaultChecked={isDefaultChecked}
        open={Boolean(anchorEl)}
        onClickCheck={onClickCheck}
        onClickForcedCheck={onClickForcedCheck}
        onClose={onClose}
      />
    </>
  );
};

export default Check;
