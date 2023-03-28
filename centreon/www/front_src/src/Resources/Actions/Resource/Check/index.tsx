import { SetStateAction, useEffect, useState } from 'react';

import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconArrow from '@mui/icons-material/KeyboardArrowDownOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import {
  labelCheck,
  labelCheckCommandSent,
  labelForcedCheck,
  labelForcedCheckCommandSent
} from '../../../translatedLabels';
import { checkEndpoint } from '../../api/endpoint';
import useAclQuery from '../aclQuery';
import ResourceActionButton from '../ResourceActionButton';

import Check from './Check';
import { adjustCheckedResources } from './helpers';

interface DisplaySelectedOption {
  checked: boolean;
  forcedChecked: boolean;
}

interface Props {
  selectedResources: Array<Resource>;
  setSelectedResources: (update: SetStateAction<Array<Resource>>) => void;
  testId: string;
}

const CheckActionButton = ({
  selectedResources,
  setSelectedResources,
  testId
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const [displaySelectedOption, setDisplaySelectedOption] =
    useState<DisplaySelectedOption | null>(null);
  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => checkEndpoint,
    method: Method.POST
  });

  const { canForcedCheck, canCheck } = useAclQuery();
  const { showSuccessMessage } = useSnackbar();

  const disableCheck = !canCheck(selectedResources);
  const disableForcedCheck = !canForcedCheck(selectedResources);
  const hasSelectedResources = selectedResources.length > 0;

  const isCheckPermitted = canCheck(selectedResources) || !hasSelectedResources;
  const isForcedCheckPermitted =
    canForcedCheck(selectedResources) || !hasSelectedResources;

  const displayPopover = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const closePopover = (): void => {
    setAnchorEl(null);
  };

  const handleCheckResource = ({ checked, forcedChecked }): void => {
    setDisplaySelectedOption({
      checked,
      forcedChecked
    });
    setAnchorEl(null);

    checkResource({
      check: { is_forced: forcedChecked },
      resources: adjustCheckedResources({ resources: selectedResources })
    }).then(() => {
      setSelectedResources([]);
      showSuccessMessage(
        checked ? t(labelCheckCommandSent) : t(labelForcedCheckCommandSent)
      );
    });
  };

  useEffect(() => {
    if (selectedResources.length <= 0 || !isNil(displaySelectedOption)) {
      return;
    }
    setDisplaySelectedOption({
      checked: canCheck(selectedResources),
      forcedChecked: canForcedCheck(selectedResources)
    });
  }, [selectedResources]);

  if (!disableCheck && displaySelectedOption?.checked) {
    return (
      <Check
        isDefaultChecked
        anchorEl={anchorEl}
        disabledButton={disableCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        icon={<IconCheck />}
        isActionPermitted={isCheckPermitted}
        labelButton={labelCheck}
        secondaryIcon={<IconArrow />}
        testId={testId}
        onClick={displayPopover}
        onClickList={{
          onClickCheck: (): void =>
            handleCheckResource({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            handleCheckResource({ checked: false, forcedChecked: true })
        }}
        onClose={closePopover}
      />
    );
  }

  if (!disableForcedCheck && displaySelectedOption?.forcedChecked) {
    return (
      <Check
        anchorEl={anchorEl}
        disabledButton={disableForcedCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        icon={<IconForcedCheck />}
        isActionPermitted={isCheckPermitted}
        isDefaultChecked={false}
        labelButton={labelForcedCheck}
        secondaryIcon={<IconArrow />}
        testId={testId}
        onClick={displayPopover}
        onClickList={{
          onClickCheck: (): void =>
            handleCheckResource({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            handleCheckResource({ checked: false, forcedChecked: true })
        }}
        onClose={closePopover}
      />
    );
  }

  return (
    <ResourceActionButton
      disabled
      icon={
        displaySelectedOption?.forcedChecked ? (
          <IconForcedCheck />
        ) : (
          <IconCheck />
        )
      }
      label={
        displaySelectedOption?.forcedChecked
          ? t(labelForcedCheck)
          : t(labelCheck)
      }
      permitted={
        displaySelectedOption?.forcedChecked
          ? isForcedCheckPermitted
          : isCheckPermitted
      }
      secondaryIcon={<IconArrow />}
      testId={testId}
      onClick={(): void => undefined}
    />
  );
};

export default CheckActionButton;
